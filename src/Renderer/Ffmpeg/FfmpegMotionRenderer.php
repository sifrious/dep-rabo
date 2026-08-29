<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Ffmpeg;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Motion\Duration;
use Sifrious\Rabo\Motion\MotionComposition;
use Sifrious\Rabo\Render\Clock;
use Sifrious\Rabo\Render\RenderArtifact;
use Sifrious\Rabo\Render\RenderCapability;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\Renderer;
use Sifrious\Rabo\Render\RenderOutcome;
use Sifrious\Rabo\Render\RenderProvenance;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\SystemClock;
use Sifrious\Rabo\Renderer\Svg\SvgFrameRenderer;
use Sifrious\Rabo\Validation\CompositionValidator;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule\MotionRule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;
use Sifrious\Rabo\Validation\ValidationReport;

/**
 * An MP4 adapter over two external tools.
 *
 * The renderer itself does not draw anything. It samples the timeline into still SVGs through
 * the same frame renderer the tests assert on, hands each to `resvg`, and hands the sequence
 * to `ffmpeg`. Rabo's own output — the frames — stays byte-reproducible; only the encode does
 * not, because MP4 bytes vary across encoder builds. `deterministic` is reported false for
 * exactly that reason rather than being quietly claimed.
 *
 * Absent tools are a deterministic refusal, not a crash: the request is fine, this renderer
 * simply cannot serve it, and a caller should ask a different one.
 */
final readonly class FfmpegMotionRenderer implements Renderer
{
    public const IDENTITY = 'rabo-ffmpeg-motion';

    public const VERSION = '1.0.0';

    public const RASTERIZER = 'resvg';

    public const ENCODER = 'ffmpeg';

    public function __construct(
        private MotionComposition $motion,
        private ?AssetStore $assets = null,
        private Clock $clock = new SystemClock(),
        private CompositionValidator $validator = new CompositionValidator(),
        private BinaryProbe $probe = new SystemBinaryProbe(),
        private ?string $workingDirectory = null,
    ) {}

    public function capabilities(): RenderCapability
    {
        $available = $this->probe->path(self::RASTERIZER) !== null && $this->probe->path(self::ENCODER) !== null;

        return new RenderCapability(
            self::IDENTITY,
            self::VERSION,
            $available ? [RenderFormat::Mp4] : [],
            7680,
            4320,
            false,
            600000,
        );
    }

    public function render(RenderRequest $request): RenderOutcome
    {
        $capability = $this->capabilities();
        if ($capability->formats === []) {
            return RenderOutcome::refused(new ValidationReport([new ValidationIssue(
                IssueCode::RendererCapabilityUnsupported,
                'renderer.'.self::IDENTITY,
                sprintf(
                    "Renderer '%s' needs %s and %s on PATH; missing: %s.",
                    self::IDENTITY, self::RASTERIZER, self::ENCODER,
                    implode(' and ', $this->missingBinaries()),
                ),
            )]));
        }

        $scene = $request->scene();
        $context = new ValidationContext(
            $request->composition, $request->brand, $request->scene, $scene,
            $this->assets, $this->motion, $capability, $request->target,
        );
        $report = $this->validator->validateScene($context)
            ->merge(new ValidationReport((new MotionRule())->check($context)));
        if (! $report->passed()) {
            return RenderOutcome::refused($report);
        }

        $fps = $request->target->framesPerSecond ?? 30;
        $timeline = $request->target->reducedMotion
            ? $this->motion->reducedMotionTimeline($request->brand)
            : $this->motion->timeline;

        $directory = ($this->workingDirectory ?? sys_get_temp_dir()).'/rabo-mp4-'.substr($request->digest(), 0, 16);
        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            return RenderOutcome::failedTransiently('workspace_unavailable', "Could not create the frame workspace at {$directory}.");
        }

        try {
            $frames = new SvgFrameRenderer($request->brand, $this->assets);
            $times = SvgFrameRenderer::frameTimes($timeline, $fps);
            foreach ($times as $index => $at) {
                $name = sprintf('%s/frame-%05d', $directory, $index);
                file_put_contents($name.'.svg', $frames->frame($scene, $timeline, $at));
                $rasterised = $this->run([
                    (string) $this->probe->path(self::RASTERIZER),
                    '--width', (string) $scene->canvas->width,
                    '--height', (string) $scene->canvas->height,
                    $name.'.svg', $name.'.png',
                ]);
                if ($rasterised['status'] !== 0) {
                    return RenderOutcome::failedTransiently('rasterizer_failed', "resvg failed on frame {$index}: ".$rasterised['output']);
                }
            }

            $output = $directory.'/out.mp4';
            $encoded = $this->run([
                (string) $this->probe->path(self::ENCODER),
                '-y', '-nostdin', '-loglevel', 'error',
                '-framerate', (string) $fps,
                '-i', $directory.'/frame-%05d.png',
                '-c:v', 'libx264', '-pix_fmt', 'yuv420p',
                '-movflags', '+faststart',
                $output,
            ]);
            if ($encoded['status'] !== 0 || ! is_file($output)) {
                return RenderOutcome::failedTransiently('encoder_failed', 'ffmpeg failed: '.$encoded['output'], 30);
            }

            $bytes = (string) file_get_contents($output);
            $artifact = new RenderArtifact(
                $bytes,
                RenderFormat::Mp4->mediaType(),
                $scene->canvas,
                $timeline->duration->milliseconds,
                $this->motion->id.($request->target->reducedMotion ? '.reduced-motion' : '').'.mp4',
            );

            return RenderOutcome::succeeded($artifact, new RenderProvenance(
                $request->composition->id,
                $request->composition->version,
                $request->composition->key(),
                $request->scene,
                $request->brand->id,
                (string) $request->brand->version,
                $request->brand->key(),
                $request->brand->referencedAssets(),
                self::IDENTITY,
                self::VERSION,
                $request->digest(),
                $artifact->digest,
                $request->composition->references,
                $this->clock->now(),
                false,
                [
                    'motion_composition' => $this->motion->key(),
                    'frames' => (string) count($times),
                    'frames_per_second' => (string) $fps,
                    self::RASTERIZER => (string) $this->probe->version(self::RASTERIZER),
                    self::ENCODER => (string) $this->probe->version(self::ENCODER),
                ],
            ));
        } finally {
            $this->cleanup($directory);
        }
    }

    /** @return list<string> */
    private function missingBinaries(): array
    {
        $missing = [];
        foreach ([self::RASTERIZER, self::ENCODER] as $binary) {
            if ($this->probe->path($binary) === null) {
                $missing[] = $binary;
            }
        }

        return $missing;
    }

    /** @param list<string> $command */
    /** @return array{status:int,output:string} */
    private function run(array $command): array
    {
        $escaped = implode(' ', array_map(escapeshellarg(...), $command));
        $output = [];
        $status = 0;
        exec($escaped.' 2>&1', $output, $status);

        return ['status' => $status, 'output' => implode("\n", array_slice($output, -5))];
    }

    private function cleanup(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}
