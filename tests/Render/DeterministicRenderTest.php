<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Render;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Composition\Dimensions;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Render\FrozenClock;
use Sifrious\Rabo\Render\RenderCapability;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\RenderOutcome;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderStatus;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Renderer\Svg\SvgStaticRenderer;
use Sifrious\Rabo\Tests\Fixture;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\ValidationIssue;
use Sifrious\Rabo\Validation\ValidationReport;

final class DeterministicRenderTest extends TestCase
{
    public function test_the_same_request_produces_byte_identical_output(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $renderer = new SvgStaticRenderer($bundle->assets, new FrozenClock());
        $request = $this->request($bundle, 'source');

        $first = $renderer->render($request);
        $second = $renderer->render($request);

        self::assertTrue($first->isSuccess());
        self::assertSame($first->artifactOrFail()->bytes, $second->artifactOrFail()->bytes);
        self::assertTrue($first->artifactOrFail()->digest->equals($second->artifactOrFail()->digest));
    }

    public function test_the_committed_artifacts_regenerate_exactly(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $renderer = new SvgStaticRenderer($bundle->assets, new FrozenClock());

        foreach (['source' => 'static.svg', 'square' => 'static-square.svg'] as $scene => $file) {
            $outcome = $renderer->render($this->request($bundle, $scene));

            self::assertTrue($outcome->isSuccess(), "Scene '{$scene}' did not render.");
            self::assertSame(
                file_get_contents(Fixture::path('expected/'.$file)),
                $outcome->artifactOrFail()->bytes,
                "The committed {$file} is not what this renderer produces.",
            );
        }
    }

    public function test_an_unsupported_target_is_a_structured_refusal_and_writes_nothing(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $renderer = new SvgStaticRenderer($bundle->assets, new FrozenClock());

        $outcome = $renderer->render(new RenderRequest(
            $bundle->composition,
            $bundle->brand,
            'source',
            new RenderTarget(RenderFormat::Mp4, $bundle->composition->scene->canvas, 24),
        ));

        self::assertSame(RenderStatus::Refused, $outcome->status);
        self::assertNull($outcome->artifact, 'A refusal must not produce an artifact.');
        self::assertTrue($outcome->report?->has(IssueCode::RendererCapabilityUnsupported));
    }

    public function test_capability_negotiation_answers_before_work_starts(): void
    {
        $capability = (new SvgStaticRenderer())->capabilities();
        $canvas = new Dimensions(1200, 630);

        self::assertTrue($capability->supports(new RenderTarget(RenderFormat::Svg, $canvas)));
        self::assertFalse($capability->supports(new RenderTarget(RenderFormat::Mp4, $canvas, 24)));
        self::assertFalse(
            (new RenderCapability('tiny', '1.0.0', [RenderFormat::Svg], 100, 100))
                ->supports(new RenderTarget(RenderFormat::Svg, $canvas)),
            'A renderer too small for the canvas does not support the target.',
        );
    }

    public function test_a_request_pinned_to_an_unsatisfiable_brand_is_rejected_on_construction(): void
    {
        $bundle = CompositionBundle::load(Fixture::path('../failing/brand-drift'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not satisfy');

        new RenderRequest($bundle->composition, $bundle->brand, 'source', new RenderTarget(RenderFormat::Svg, $bundle->composition->scene->canvas));
    }

    public function test_the_four_outcome_states_stay_distinguishable(): void
    {
        $refusal = RenderOutcome::refused(new ValidationReport([
            new ValidationIssue(IssueCode::AssetMissing, 'logo', 'Missing.'),
        ]));
        $transient = RenderOutcome::failedTransiently('encoder_failed', 'ffmpeg died.', 30);
        $acknowledged = RenderOutcome::acknowledged('provider-req-1');

        self::assertSame(RenderStatus::Refused, $refusal->status);
        self::assertFalse($refusal->status->isSafeToRetry(), 'A refusal will not become a success on retry.');

        self::assertSame(RenderStatus::FailedTransiently, $transient->status);
        self::assertTrue($transient->status->isSafeToRetry());
        self::assertSame(30, $transient->retryAfterSeconds);

        self::assertSame(RenderStatus::Acknowledged, $acknowledged->status);
        self::assertFalse($acknowledged->status->isSafeToRetry(), 'Resubmitting an acknowledged request may duplicate work.');
        self::assertSame('provider-req-1', $acknowledged->providerRequestId);
    }

    public function test_a_refusal_cannot_be_built_from_a_passing_report(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RenderOutcome::refused(new ValidationReport());
    }

    public function test_asking_a_failed_outcome_for_its_artifact_fails_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RenderOutcome::failedTransiently('encoder_failed', 'ffmpeg died.')->artifactOrFail();
    }

    private function request(CompositionBundle $bundle, string $scene): RenderRequest
    {
        $canvas = $bundle->composition->allScenes()[$scene]->canvas;

        return new RenderRequest($bundle->composition, $bundle->brand, $scene, new RenderTarget(RenderFormat::Svg, $canvas));
    }
}
