<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Motion;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Motion\CubicBezier;
use Sifrious\Rabo\Motion\Cue;
use Sifrious\Rabo\Motion\CueEffect;
use Sifrious\Rabo\Motion\CueSampler;
use Sifrious\Rabo\Motion\Duration;
use Sifrious\Rabo\Motion\Easing;
use Sifrious\Rabo\Motion\MotionComposition;
use Sifrious\Rabo\Motion\ReducedMotionStrategy;
use Sifrious\Rabo\Motion\Timeline;
use Sifrious\Rabo\Motion\Track;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Render\FrozenClock;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Renderer\Svg\SvgFrameRenderer;
use Sifrious\Rabo\Renderer\Svg\SvgMotionRenderer;
use Sifrious\Rabo\Tests\Fixture;

final class MotionContractTest extends TestCase
{
    public function test_the_canonical_motion_is_fifteen_seconds_over_the_same_composition(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $motion = $bundle->motionOrFail();

        self::assertSame(15000, $motion->timeline->duration->milliseconds);
        self::assertTrue($motion->appliesTo($bundle->composition), 'Motion must attach to a scene the composition actually has.');
        self::assertSame('source', $motion->scene);
        self::assertCount(9, $motion->timeline->cues());
    }

    public function test_cue_order_is_derived_from_time_not_from_authoring_order(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $cues = $bundle->motionOrFail()->timeline->cues();

        $starts = array_map(static fn (Cue $cue): int => $cue->start->milliseconds, $cues);
        $sorted = $starts;
        sort($sorted);

        self::assertSame($sorted, $starts);
        self::assertSame('reveal-done', $cues[0]->id);
        self::assertSame('reveal-verified', $cues[count($cues) - 1]->id);
    }

    public function test_a_timeline_built_from_shuffled_tracks_is_the_same_timeline(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $tracks = $bundle->motionOrFail()->timeline->tracks;

        $forward = new Timeline(new Duration(15000), $tracks);
        $backward = new Timeline(new Duration(15000), array_reverse($tracks));

        self::assertSame($forward->toArray(), $backward->toArray());
    }

    public function test_the_canonical_timeline_has_no_stray_or_conflicting_cues(): void
    {
        $timeline = CompositionBundle::load(Fixture::path())->motionOrFail()->timeline;

        self::assertSame([], $timeline->cuesBeyondEnd());
        self::assertSame([], $timeline->conflicts());
    }

    public function test_a_cue_must_have_a_duration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cue('instant', new NodeId('headline'), CueEffect::Reveal, new Duration(0), new Duration(0));
    }

    public function test_overlap_is_only_a_conflict_on_the_same_node(): void
    {
        $first = new Cue('a', new NodeId('one'), CueEffect::Reveal, new Duration(0), new Duration(1000));
        $sameNode = new Cue('b', new NodeId('one'), CueEffect::Reveal, new Duration(500), new Duration(1000));
        $otherNode = new Cue('c', new NodeId('two'), CueEffect::Reveal, new Duration(500), new Duration(1000));

        self::assertTrue($first->overlaps($sameNode));
        self::assertFalse($first->overlaps($otherNode), 'Two nodes animating at once is a composition, not a conflict.');
    }

    public function test_the_reduced_motion_alternative_is_derived_from_the_same_timeline(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $motion = $bundle->motionOrFail();
        $reduced = $motion->reducedMotionTimeline($bundle->brand);

        self::assertSame(ReducedMotionStrategy::FinalState, $motion->reducedMotion);
        self::assertSame($motion->timeline->duration->milliseconds, $reduced->duration->milliseconds);
        self::assertSame([], $reduced->cues(), 'The final-state alternative holds the composed end state.');
    }

    public function test_a_cross_fade_alternative_keeps_the_beats_but_removes_the_movement(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $source = $bundle->motionOrFail();
        $crossFade = new MotionComposition(
            $source->id, $source->version, $source->compositionId, $source->scene,
            $source->timeline, ReducedMotionStrategy::CrossFadeOnly, $source->description,
        );

        $reduced = $crossFade->reducedMotionTimeline($bundle->brand);

        self::assertCount(count($source->timeline->cues()), $reduced->cues());
        foreach ($reduced->cues() as $cue) {
            self::assertSame($bundle->brand->motion->durationMs('fast'), $cue->duration->milliseconds);
            self::assertSame(Easing::Linear, $cue->easing);
        }
    }

    public function test_every_essential_node_is_still_visible_when_the_timeline_ends(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $timeline = $bundle->motionOrFail()->timeline;

        foreach ($timeline->nodes() as $node) {
            self::assertTrue($timeline->nodeVisibleAtEnd($node), "Node '{$node}' is gone by the end of the piece.");
        }
    }

    public function test_the_committed_motion_artifacts_regenerate_exactly(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $renderer = new SvgMotionRenderer($bundle->motionOrFail(), $bundle->assets, new FrozenClock());
        $canvas = $bundle->composition->scene->canvas;

        foreach ([false => 'motion.svg', true => 'reduced-motion.svg'] as $reduced => $file) {
            $outcome = $renderer->render(new RenderRequest(
                $bundle->composition, $bundle->brand, 'source',
                new RenderTarget(RenderFormat::SvgAnimated, $canvas, 24, (bool) $reduced),
            ));

            self::assertTrue($outcome->isSuccess());
            self::assertSame(file_get_contents(Fixture::path('expected/'.$file)), $outcome->artifactOrFail()->bytes);
        }
    }

    public function test_the_reduced_motion_artifact_carries_no_animation(): void
    {
        $reduced = (string) file_get_contents(Fixture::path('expected/reduced-motion.svg'));
        $animated = (string) file_get_contents(Fixture::path('expected/motion.svg'));

        self::assertStringNotContainsString('animation:', $reduced);
        self::assertStringContainsString('animation:', $animated);
        self::assertStringContainsString('Agent completion ≠ verified completion', $reduced, 'The alternative still says the thing.');
    }

    public function test_sampled_frames_follow_the_brand_easing_rather_than_a_straight_ramp(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $sampler = new CueSampler($bundle->brand);
        $timeline = $bundle->motionOrFail()->timeline;

        $start = $sampler->stateAt($timeline, 'step-done', new Duration(0));
        $middle = $sampler->stateAt($timeline, 'step-done', new Duration(170));
        $end = $sampler->stateAt($timeline, 'step-done', new Duration(340));

        self::assertSame(0.0, $start->opacity);
        self::assertSame(1.0, $end->opacity);
        self::assertGreaterThan(0.5, $middle->opacity, "The brand's quiet curve front-loads, so halfway through is past halfway.");
    }

    public function test_frame_sampling_covers_the_whole_timeline_deterministically(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $timeline = $bundle->motionOrFail()->timeline;

        $times = SvgFrameRenderer::frameTimes($timeline, 24);

        self::assertCount(360, $times);
        self::assertSame(0, $times[0]->milliseconds);
        self::assertLessThan($timeline->duration->milliseconds, $times[359]->milliseconds);
        self::assertSame(
            array_map(static fn (Duration $d): int => $d->milliseconds, $times),
            array_map(static fn (Duration $d): int => $d->milliseconds, SvgFrameRenderer::frameTimes($timeline, 24)),
        );
    }

    public function test_frames_are_byte_reproducible_even_though_the_encode_is_not(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $frames = new SvgFrameRenderer($bundle->brand, $bundle->assets);
        $timeline = $bundle->motionOrFail()->timeline;

        $first = $frames->frame($bundle->composition->scene, $timeline, new Duration(7000));
        $second = $frames->frame($bundle->composition->scene, $timeline, new Duration(7000));

        self::assertSame($first, $second);
    }

    public function test_the_easing_solver_matches_known_points(): void
    {
        $linear = CubicBezier::parse('linear');
        $quiet = CubicBezier::parse('cubic-bezier(0.2, 0, 0, 1)');

        self::assertSame(0.0, $quiet->at(0.0));
        self::assertSame(1.0, $quiet->at(1.0));
        self::assertEqualsWithDelta(0.5, $linear->at(0.5), 0.0001);
        self::assertGreaterThan($linear->at(0.5), $quiet->at(0.5));
    }

    public function test_an_unsupported_timing_function_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CubicBezier::parse('ease-in-out-back');
    }

    public function test_a_track_named_twice_is_rejected(): void
    {
        $cue = new Cue('a', new NodeId('one'), CueEffect::Reveal, new Duration(0), new Duration(100));

        $this->expectException(InvalidArgumentException::class);

        new Timeline(new Duration(1000), [new Track('main', [$cue]), new Track('main', [])]);
    }
}
