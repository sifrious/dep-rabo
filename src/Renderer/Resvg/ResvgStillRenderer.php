<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Resvg;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Render\Clock;
use Sifrious\Rabo\Render\RenderArtifact;
use Sifrious\Rabo\Render\RenderCapability;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\Renderer;
use Sifrious\Rabo\Render\RenderOutcome;
use Sifrious\Rabo\Render\RenderProvenance;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderStatus;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Render\SystemClock;
use Sifrious\Rabo\Renderer\BinaryProbe;
use Sifrious\Rabo\Renderer\Svg\SvgStaticRenderer;
use Sifrious\Rabo\Renderer\SystemBinaryProbe;
use Sifrious\Rabo\Validation\CompositionValidator;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\ValidationIssue;
use Sifrious\Rabo\Validation\ValidationReport;

/**
 * A still PNG adapter over `resvg`.
 *
 * `RenderFormat::Png` had been part of the published contract since the renderer boundary was
 * defined, and nothing produced one — a capability advertised and not implemented. This is that
 * gap closed, for a caller that needs pixels rather than a document: a social platform will not
 * take an SVG.
 *
 * It draws nothing itself. The scene is painted by `SvgStaticRenderer`, so the PNG is a
 * rasterization of exactly the artifact the SVG renderer would have produced — validation,
 * refusals, accessibility text and font embedding all come from there rather than being written
 * a second time and drifting.
 *
 * `deterministic` is false, for the same reason the MP4 adapter reports false: raster output
 * varies across `resvg` builds. The SVG feeding it is byte-reproducible and is what the tests
 * assert on.
 */
final readonly class ResvgStillRenderer implements Renderer
{
    public const IDENTITY = 'rabo-resvg-still';

    public const VERSION = '1.0.0';

    public function __construct(
        private ?AssetStore $assets = null,
        private Clock $clock = new SystemClock(),
        private CompositionValidator $validator = new CompositionValidator(),
        private BinaryProbe $probe = new SystemBinaryProbe(),
        private ?string $workingDirectory = null,
    ) {}

    public function capabilities(): RenderCapability
    {
        $rasterizer = new Rasterizer($this->assets, $this->probe);

        return new RenderCapability(
            self::IDENTITY,
            self::VERSION,
            $rasterizer->available() ? [RenderFormat::Png] : [],
            16384,
            16384,
            false,
        );
    }

    public function render(RenderRequest $request): RenderOutcome
    {
        $rasterizer = new Rasterizer($this->assets, $this->probe);
        if (! $rasterizer->available()) {
            return RenderOutcome::refused(new ValidationReport([new ValidationIssue(
                IssueCode::RendererCapabilityUnsupported,
                'renderer.'.self::IDENTITY,
                sprintf("Renderer '%s' needs %s on PATH, and it is not there.", self::IDENTITY, Rasterizer::BINARY),
            )]));
        }

        $scene = $request->scene();

        // The SVG renderer validates, and refuses on its own terms. Its refusal is this one.
        $svg = (new SvgStaticRenderer($this->assets, $this->clock, $this->validator))->render(new RenderRequest(
            $request->composition,
            $request->brand,
            $request->scene,
            new RenderTarget(RenderFormat::Svg, $request->target->dimensions, null, $request->target->reducedMotion, $request->target->embedFonts),
        ));
        if ($svg->status !== RenderStatus::Succeeded) {
            return $svg;
        }

        // After validation, as the MP4 adapter does it: a composition that is invalid is refused on
        // its own terms, whatever this renderer's tooling can or cannot do.
        $unrasterizable = $rasterizer->familiesWithoutRasterFile($request->brand, $scene);
        if ($unrasterizable !== []) {
            // Skipping them silently is what produced a video in a system fallback while the SVG
            // rendered in the brand. The same trap is here, so the same refusal is.
            return RenderOutcome::refused(new ValidationReport([new ValidationIssue(
                IssueCode::RendererCapabilityUnsupported,
                'brand.typography',
                sprintf(
                    "Renderer '%s' rasterizes text from TrueType files, and this scene sets type in %s, which ships none.",
                    self::IDENTITY,
                    implode(', ', $unrasterizable),
                ),
            )]));
        }

        $directory = ($this->workingDirectory ?? sys_get_temp_dir()).'/rabo-png-'.substr($request->digest(), 0, 16);
        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            return RenderOutcome::failedTransiently('workspace_unavailable', "Could not create the raster workspace at {$directory}.");
        }

        try {
            $svgPath = $directory.'/scene.svg';
            $pngPath = $directory.'/scene.png';
            file_put_contents($svgPath, $svg->artifactOrFail()->bytes);

            $fontArguments = $rasterizer->fontArguments($request->brand, $scene, $directory);
            $result = $rasterizer->rasterize(
                $svgPath, $pngPath, $scene->canvas->width, $scene->canvas->height, $fontArguments,
            );
            if ($result['status'] !== 0 || ! is_file($pngPath)) {
                return RenderOutcome::failedTransiently('rasterizer_failed', Rasterizer::BINARY.' failed: '.$result['output']);
            }

            $artifact = new RenderArtifact(
                (string) file_get_contents($pngPath),
                RenderFormat::Png->mediaType(),
                $scene->canvas,
                null,
                $request->composition->id.'.'.$request->scene.'.png',
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
                    'embedded_fonts' => (string) (count($fontArguments) / 2),
                    'source_svg_digest' => (string) $svg->artifactOrFail()->digest,
                    Rasterizer::BINARY => (string) $rasterizer->version(),
                ],
            ));
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
