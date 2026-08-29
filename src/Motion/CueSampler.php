<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

use Sifrious\Rabo\Brand\BrandLibrary;

/**
 * Reads a timeline at an instant.
 *
 * The keyframes here mirror the ones the animated SVG declares, so a frame-sampled encode and
 * a browser-played animation show the same thing at the same moment. Keeping the two in one
 * file would be better still; keeping them in one package, both derived from the brand's own
 * easing tokens, is what is achievable without a shared animation engine.
 */
final readonly class CueSampler
{
    /** Matches the `translateY` in the animated SVG's reveal keyframe. */
    public const REVEAL_OFFSET_PX = 8.0;

    public function __construct(private BrandLibrary $brand) {}

    public function stateAt(Timeline $timeline, string $nodeId, Duration $at): NodeState
    {
        $state = new NodeState(1.0);
        foreach ($timeline->cues() as $cue) {
            if ($cue->node->value !== $nodeId) {
                continue;
            }
            $state = $this->applyCue($cue, $at);
        }

        return $state;
    }

    private function applyCue(Cue $cue, Duration $at): NodeState
    {
        $elapsed = $at->milliseconds - $cue->start->milliseconds;

        if ($elapsed < 0) {
            return match ($cue->effect) {
                CueEffect::Reveal => new NodeState(0.0, self::REVEAL_OFFSET_PX),
                CueEffect::Emphasise, CueEffect::Dismiss => new NodeState(1.0),
            };
        }

        $fraction = $cue->duration->milliseconds === 0
            ? 1.0
            : min(1.0, $elapsed / $cue->duration->milliseconds);
        $progress = $this->curveFor($cue)->at($fraction);

        return match ($cue->effect) {
            CueEffect::Reveal => new NodeState($progress, self::REVEAL_OFFSET_PX * (1 - $progress)),
            CueEffect::Dismiss => new NodeState(1 - $progress),
            CueEffect::Emphasise => new NodeState($progress < 0.5 ? 1 - 0.9 * $progress : 0.55 + 0.9 * ($progress - 0.5)),
        };
    }

    private function curveFor(Cue $cue): CubicBezier
    {
        $name = $cue->easing->value;

        return CubicBezier::parse($this->brand->motion->hasEasing($name) ? $this->brand->motion->easing($name) : 'linear');
    }
}
