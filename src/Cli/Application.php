<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Cli;

use InvalidArgumentException;
use JsonException;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Composition\Dimensions;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Render\Clock;
use Sifrious\Rabo\Render\FrozenClock;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\RenderOutcome;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderStatus;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Renderer\Ffmpeg\FfmpegMotionRenderer;
use Sifrious\Rabo\Renderer\Svg\SvgMotionRenderer;
use Sifrious\Rabo\Renderer\Svg\SvgStaticRenderer;
use Sifrious\Rabo\Validation\CompositionValidator;
use Sifrious\Rabo\Validation\Rule\MotionRule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationReport;
use Throwable;

/**
 * The `rabo` command.
 *
 * Three verbs, each of which either succeeds with an artifact or exits non-zero with a
 * machine-readable reason. Nothing here holds domain logic: the command is a way to run the
 * package from a terminal, not a second place where rules live.
 */
final readonly class Application
{
    public function __construct(
        private Clock $clock = new FrozenClock(),
        private CompositionValidator $validator = new CompositionValidator(),
    ) {}

    /** @param list<string> $argv */
    public function run(array $argv, mixed $out = STDOUT, mixed $err = STDERR): int
    {
        $command = $argv[1] ?? 'help';
        $arguments = array_slice($argv, 2);

        try {
            return match ($command) {
                'validate' => $this->validate($arguments, $out, $err),
                'render' => $this->render($arguments, $out, $err),
                'inspect' => $this->inspect($arguments, $out, $err),
                'help', '--help', '-h' => $this->help($out),
                default => $this->fail($err, "Unknown command '{$command}'. Try: validate, render, inspect."),
            };
        } catch (InvalidArgumentException|JsonException $failure) {
            return $this->fail($err, $failure->getMessage());
        } catch (Throwable $failure) {
            return $this->fail($err, $failure::class.': '.$failure->getMessage());
        }
    }

    /** @param list<string> $arguments */
    private function validate(array $arguments, mixed $out, mixed $err): int
    {
        $options = Options::parse($arguments, [], []);
        $bundle = CompositionBundle::load($options->positional(0, 'a composition bundle directory'));

        $report = $this->validator->validate(
            $bundle->composition,
            $bundle->brand,
            $bundle->assets,
            null,
            null,
            $bundle->assetRecords,
        );
        if ($bundle->hasMotion()) {
            $report = $report->merge($this->validateMotion($bundle));
        }

        fwrite($out, $this->encode($report->toArray())."\n");
        if ($report->passed()) {
            fwrite($err, "PASS {$bundle->composition->id}: no blocking issues.\n");

            return 0;
        }
        fwrite($err, sprintf("FAIL %s: %d blocking issue(s).\n", $bundle->composition->id, count($report->errors())));
        foreach ($report->errors() as $issue) {
            fwrite($err, sprintf("  %s at %s: %s\n", $issue->code->value, $issue->path, $issue->message));
        }

        return 1;
    }

    /** @param list<string> $arguments */
    private function render(array $arguments, mixed $out, mixed $err): int
    {
        $options = Options::parse(
            $arguments,
            ['format', 'scene', 'out', 'fps', 'name'],
            ['reduced-motion', 'no-embed-fonts'],
        );
        $bundle = CompositionBundle::load($options->positional(0, 'a composition bundle directory'));

        $format = RenderFormat::tryFrom($options->value('format', RenderFormat::Svg->value))
            ?? throw new InvalidArgumentException('Unknown --format. Try: svg, svg-animated, mp4.');
        $sceneName = $options->value('scene', 'source');
        $reduced = $options->flag('reduced-motion');
        $embedFonts = ! $options->flag('no-embed-fonts');
        $directory = rtrim($options->value('out', 'build'), '/');
        $fps = $format->isTemporal() ? (int) $options->value('fps', '24') : null;

        $scene = $bundle->composition->allScenes()[$sceneName]
            ?? throw new InvalidArgumentException("Composition '{$bundle->composition->id}' has no scene '{$sceneName}'.");

        $renderer = match ($format) {
            RenderFormat::Svg => new SvgStaticRenderer($bundle->assets, $this->clock, $this->validator),
            RenderFormat::SvgAnimated => new SvgMotionRenderer($bundle->motionOrFail(), $bundle->assets, $this->clock, $this->validator),
            RenderFormat::Mp4 => new FfmpegMotionRenderer($bundle->motionOrFail(), $bundle->assets, $this->clock, $this->validator),
            RenderFormat::Png => throw new InvalidArgumentException('No bundled renderer produces PNG; rasterize the SVG instead.'),
        };

        $outcome = $renderer->render(new RenderRequest(
            $bundle->composition,
            $bundle->brand,
            $sceneName,
            new RenderTarget($format, new Dimensions($scene->canvas->width, $scene->canvas->height), $fps, $reduced, $embedFonts),
        ));

        return $this->writeOutcome($outcome, $directory, $options->value('name', $this->defaultName($sceneName, $format, $reduced)), $out, $err);
    }

    /** @param list<string> $arguments */
    private function inspect(array $arguments, mixed $out, mixed $err): int
    {
        $options = Options::parse($arguments, [], []);
        $artifact = $options->positional(0, 'a rendered artifact');
        $provenanceFile = preg_replace('/\.[a-z0-9]+$/i', '', $artifact).'.provenance.json';

        if (! is_file($artifact)) {
            return $this->fail($err, "No artifact at {$artifact}.");
        }
        if (! is_file((string) $provenanceFile)) {
            return $this->fail($err, "No provenance beside {$artifact}. Re-render it with `rabo render`.");
        }

        $provenance = json_decode((string) file_get_contents((string) $provenanceFile), true, flags: JSON_THROW_ON_ERROR);
        $actual = ContentDigest::ofBytes((string) file_get_contents($artifact));
        $recorded = is_array($provenance) ? ($provenance['output_digest'] ?? null) : null;

        fwrite($out, $this->encode([
            'artifact' => $artifact,
            'actual_digest' => (string) $actual,
            'matches_provenance' => $recorded === (string) $actual,
            'provenance' => $provenance,
        ])."\n");

        if ($recorded !== (string) $actual) {
            fwrite($err, "FAIL {$artifact}: the bytes on disk do not match the digest its provenance records.\n");

            return 1;
        }
        fwrite($err, "PASS {$artifact}: bytes match the recorded output digest.\n");

        return 0;
    }

    private function validateMotion(CompositionBundle $bundle): ValidationReport
    {
        $motion = $bundle->motionOrFail();
        $scene = $bundle->composition->allScenes()[$motion->scene];

        return new ValidationReport((new MotionRule())->check(
            new ValidationContext($bundle->composition, $bundle->brand, $motion->scene, $scene, $bundle->assets, $motion),
        ));
    }

    private function writeOutcome(RenderOutcome $outcome, string $directory, string $name, mixed $out, mixed $err): int
    {
        if ($outcome->status !== RenderStatus::Succeeded) {
            fwrite($out, $this->encode($outcome->toArray())."\n");
            fwrite($err, "FAIL render {$outcome->status->value}.\n");
            foreach ($outcome->report?->errors() ?? [] as $issue) {
                fwrite($err, sprintf("  %s at %s: %s\n", $issue->code->value, $issue->path, $issue->message));
            }
            if ($outcome->code !== null) {
                fwrite($err, "  {$outcome->code}: {$outcome->message}\n");
            }

            return 1;
        }

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            return $this->fail($err, "Could not create {$directory}.");
        }

        $artifact = $outcome->artifactOrFail();
        $extension = pathinfo($artifact->filename ?? 'artifact.bin', PATHINFO_EXTENSION);
        $artifactPath = "{$directory}/{$name}.{$extension}";
        file_put_contents($artifactPath, $artifact->bytes);
        file_put_contents("{$directory}/{$name}.provenance.json", $this->encode($outcome->provenance?->toArray() ?? [])."\n");

        fwrite($out, $this->encode([
            'status' => $outcome->status->value,
            'artifact' => $artifactPath,
            'digest' => (string) $artifact->digest,
            'byte_length' => $artifact->byteLength(),
            'duration_ms' => $artifact->durationMs,
        ])."\n");
        fwrite($err, "PASS wrote {$artifactPath}\n");

        return 0;
    }

    private function defaultName(string $scene, RenderFormat $format, bool $reduced): string
    {
        if ($format === RenderFormat::Svg) {
            return $scene === 'source' ? 'static' : 'static-'.$scene;
        }

        return $reduced ? 'reduced-motion' : 'motion';
    }

    /** @param array<string,mixed> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function help(mixed $out): int
    {
        fwrite($out, <<<'TEXT'
        rabo — portable brand compositions

          rabo validate <bundle>
              Validate a composition bundle. Prints a JSON report; exits 1 on any blocking issue.

          rabo render <bundle> [--format=svg|svg-animated|mp4] [--scene=source|<variant>]
                               [--reduced-motion] [--no-embed-fonts] [--fps=24]
                               [--out=build] [--name=<basename>]
              Render one scene. Writes the artifact and its provenance beside it.
              --no-embed-fonts leaves the brand's typefaces out. Smaller, and correct
              only where you control what is installed on the display machine.

          rabo inspect <artifact>
              Re-hash a rendered artifact and check it against the provenance written with it.

        TEXT);

        return 0;
    }

    private function fail(mixed $err, string $message): int
    {
        fwrite($err, "ERROR {$message}\n");

        return 2;
    }
}
