<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Render;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Composition\Dimensions;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Render\FrozenClock;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderStatus;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Renderer\BinaryProbe;
use Sifrious\Rabo\Renderer\Resvg\Rasterizer;
use Sifrious\Rabo\Renderer\Resvg\ResvgStillRenderer;
use Sifrious\Rabo\Tests\Fixture;
use Sifrious\Rabo\Validation\IssueCode;

/**
 * The PNG adapter.
 *
 * `RenderFormat::Png` was part of the published contract from the start and nothing produced one —
 * an advertised capability that threw. The tests that matter without `resvg` installed are the
 * refusals, and CI has no `resvg`, so those are the ones that run everywhere.
 */
final class PngRenderTest extends TestCase
{
    public function test_it_declares_png_only_when_the_rasterizer_is_there(): void
    {
        self::assertSame(
            [RenderFormat::Png],
            $this->renderer(true)->capabilities()->formats,
        );
        self::assertSame(
            [],
            $this->renderer(false)->capabilities()->formats,
            'A renderer that cannot run declares nothing, rather than promising and failing.',
        );
    }

    public function test_a_missing_rasterizer_is_a_refusal_naming_the_binary(): void
    {
        $outcome = $this->renderer(false)->render($this->request());

        self::assertSame(RenderStatus::Refused, $outcome->status);
        self::assertSame([IssueCode::RendererCapabilityUnsupported->value], $outcome->report?->codes());
        self::assertStringContainsString(Rasterizer::BINARY, $outcome->report?->issues[0]->message ?? '');
        self::assertNull($outcome->artifact, 'A refusal carries no artifact.');
    }

    public function test_it_never_claims_to_be_deterministic(): void
    {
        self::assertFalse(
            $this->renderer(true)->capabilities()->deterministic,
            'Raster bytes vary across resvg builds; the SVG feeding this is what the tests assert on.',
        );
    }

    public function test_a_composition_that_fails_validation_produces_no_png(): void
    {
        if (! (new Rasterizer())->available()) {
            self::markTestSkipped('resvg is not installed.');
        }

        $bundle = CompositionBundle::load(dirname(__DIR__, 2).'/fixtures/failing/insufficient-contrast');
        $outcome = (new ResvgStillRenderer($bundle->assets, new FrozenClock()))->render(new RenderRequest(
            $bundle->composition,
            $bundle->brand,
            'source',
            new RenderTarget(RenderFormat::Png, new Dimensions(600, 300)),
        ));

        self::assertSame(RenderStatus::Refused, $outcome->status);
        self::assertContains(IssueCode::ContrastInsufficient->value, $outcome->report?->codes() ?? []);
        self::assertNull($outcome->artifact);
    }

    public function test_it_rasterizes_the_scene_at_its_canvas_size(): void
    {
        if (! (new Rasterizer())->available()) {
            self::markTestSkipped('resvg is not installed.');
        }

        $outcome = $this->realRenderer()->render($this->request());

        self::assertSame(RenderStatus::Succeeded, $outcome->status);
        $bytes = $outcome->artifactOrFail()->bytes;

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes, 'That is not a PNG.');
        [$width, $height] = getimagesizefromstring($bytes);
        self::assertSame(1200, $width);
        self::assertSame(630, $height);
        self::assertSame('image/png', $outcome->artifactOrFail()->mediaType);
    }

    public function test_its_provenance_names_the_svg_it_rasterized(): void
    {
        if (! (new Rasterizer())->available()) {
            self::markTestSkipped('resvg is not installed.');
        }

        $provenance = $this->realRenderer()->render($this->request())->provenance;

        self::assertSame(ResvgStillRenderer::IDENTITY, $provenance?->renderer);
        self::assertFalse($provenance?->deterministic);
        self::assertArrayHasKey('source_svg_digest', $provenance?->toolVersions ?? []);
        self::assertSame('3', $provenance?->toolVersions['embedded_fonts'] ?? null, 'The brand faces are handed to the rasterizer.');
    }

    private function request(): RenderRequest
    {
        $bundle = CompositionBundle::load(Fixture::path());

        return new RenderRequest(
            $bundle->composition,
            $bundle->brand,
            'source',
            new RenderTarget(RenderFormat::Png, new Dimensions(1200, 630)),
        );
    }

    private function realRenderer(): ResvgStillRenderer
    {
        return new ResvgStillRenderer(CompositionBundle::load(Fixture::path())->assets, new FrozenClock());
    }

    private function renderer(bool $present): ResvgStillRenderer
    {
        $bundle = CompositionBundle::load(Fixture::path());

        return new ResvgStillRenderer($bundle->assets, new FrozenClock(), probe: $this->probe($present));
    }

    private function probe(bool $present): BinaryProbe
    {
        return new class($present) implements BinaryProbe
        {
            public function __construct(private bool $present) {}

            public function path(string $binary): ?string
            {
                return $this->present ? '/usr/bin/'.$binary : null;
            }

            public function version(string $binary): ?string
            {
                return $this->present ? $binary.' 1.0.0' : null;
            }
        };
    }
}
