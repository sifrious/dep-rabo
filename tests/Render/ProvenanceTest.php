<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Render;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Render\FrozenClock;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Renderer\Ffmpeg\BinaryProbe;
use Sifrious\Rabo\Renderer\Ffmpeg\FfmpegMotionRenderer;
use Sifrious\Rabo\Renderer\Svg\SvgStaticRenderer;
use Sifrious\Rabo\Render\RenderStatus;
use Sifrious\Rabo\Reference\ReferenceRole;
use Sifrious\Rabo\Tests\Fixture;
use Sifrious\Rabo\Validation\IssueCode;

final class ProvenanceTest extends TestCase
{
    public function test_a_result_names_the_exact_composition_and_brand_it_came_from(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $outcome = $this->render($bundle);
        $provenance = $outcome->provenance;

        self::assertNotNull($provenance);
        self::assertSame($bundle->composition->id, $provenance->compositionId);
        self::assertSame($bundle->composition->version, $provenance->compositionVersion);
        self::assertSame($bundle->composition->key(), $provenance->compositionKey);
        self::assertSame($bundle->brand->id, $provenance->brandId);
        self::assertSame((string) $bundle->brand->version, $provenance->brandVersion);
        self::assertSame($bundle->brand->key(), $provenance->brandKey);
        self::assertSame('source', $provenance->scene);
    }

    public function test_a_result_records_the_renderer_identity_and_version(): void
    {
        $provenance = $this->render(CompositionBundle::load(Fixture::path()))->provenance;

        self::assertSame(SvgStaticRenderer::IDENTITY, $provenance?->renderer);
        self::assertSame(SvgStaticRenderer::VERSION, $provenance?->rendererVersion);
        self::assertTrue($provenance?->deterministic);
    }

    public function test_a_result_records_the_artifact_digest_and_the_asset_digests(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $outcome = $this->render($bundle);

        self::assertTrue($outcome->provenance?->outputDigest->equals($outcome->artifactOrFail()->digest));
        self::assertSame(
            array_map(strval(...), $bundle->brand->referencedAssets()),
            array_map(strval(...), $outcome->provenance?->assets ?? []),
        );
    }

    public function test_the_recorded_digest_verifies_against_the_bytes_on_disk(): void
    {
        foreach (['static', 'static-square', 'motion', 'reduced-motion'] as $name) {
            $bytes = (string) file_get_contents(Fixture::path("expected/{$name}.svg"));
            $provenance = json_decode((string) file_get_contents(Fixture::path("expected/{$name}.provenance.json")), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(
                (string) ContentDigest::ofBytes($bytes),
                $provenance['output_digest'],
                "Committed {$name}.svg does not match the digest its provenance records.",
            );
        }
    }

    public function test_provenance_carries_the_upstream_references_forward(): void
    {
        $references = $this->render(CompositionBundle::load(Fixture::path()))->provenance?->references;

        self::assertTrue($references?->has(ReferenceRole::Treatment));
        self::assertTrue($references?->has(ReferenceRole::Evidence));
        self::assertSame('sifrious/pulp', $references?->one(ReferenceRole::Treatment)?->owner);
        self::assertCount(2, $references?->all(ReferenceRole::Evidence) ?? []);
    }

    public function test_the_request_digest_distinguishes_different_requests(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $canvas = $bundle->composition->scene->canvas;

        $source = new RenderRequest($bundle->composition, $bundle->brand, 'source', new RenderTarget(RenderFormat::Svg, $canvas));
        $again = new RenderRequest($bundle->composition, $bundle->brand, 'source', new RenderTarget(RenderFormat::Svg, $canvas));
        $square = new RenderRequest($bundle->composition, $bundle->brand, 'square', new RenderTarget(RenderFormat::Svg, $bundle->composition->variant('square')->canvas));

        self::assertSame($source->digest(), $again->digest());
        self::assertNotSame($source->digest(), $square->digest());
    }

    public function test_a_render_request_survives_being_serialized_to_another_process(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $request = new RenderRequest(
            $bundle->composition, $bundle->brand, 'source',
            new RenderTarget(RenderFormat::Svg, $bundle->composition->scene->canvas),
        );

        $restored = RenderRequest::fromArray(json_decode(json_encode($request, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));

        self::assertSame($request->digest(), $restored->digest());
    }

    public function test_the_encoder_adapter_refuses_deterministically_when_its_tools_are_absent(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $renderer = new FfmpegMotionRenderer(
            $bundle->motionOrFail(),
            $bundle->assets,
            new FrozenClock(),
            probe: $this->probe(hasBinaries: false),
        );

        self::assertSame([], $renderer->capabilities()->formats);

        $outcome = $renderer->render(new RenderRequest(
            $bundle->composition, $bundle->brand, 'source',
            new RenderTarget(RenderFormat::Mp4, $bundle->composition->scene->canvas, 24),
        ));

        self::assertSame(RenderStatus::Refused, $outcome->status);
        self::assertTrue($outcome->report?->has(IssueCode::RendererCapabilityUnsupported));
        self::assertNull($outcome->artifact);
    }

    public function test_the_encoder_adapter_never_claims_determinism_it_cannot_deliver(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $capability = (new FfmpegMotionRenderer($bundle->motionOrFail(), probe: $this->probe(hasBinaries: true)))->capabilities();

        self::assertSame([RenderFormat::Mp4], $capability->formats);
        self::assertFalse($capability->deterministic, 'MP4 bytes vary across encoder builds; the capability must say so.');
    }

    private function render(CompositionBundle $bundle): \Sifrious\Rabo\Render\RenderOutcome
    {
        return (new SvgStaticRenderer($bundle->assets, new FrozenClock()))->render(new RenderRequest(
            $bundle->composition,
            $bundle->brand,
            'source',
            new RenderTarget(RenderFormat::Svg, $bundle->composition->scene->canvas),
        ));
    }

    private function probe(bool $hasBinaries): BinaryProbe
    {
        return new class($hasBinaries) implements BinaryProbe {
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
