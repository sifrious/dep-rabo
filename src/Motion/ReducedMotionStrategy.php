<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

/**
 * How a timeline becomes an accessible alternative.
 *
 * The strategy is authored, not inferred. "Remove the animation" is a design decision with
 * consequences for what the viewer can still learn, so the brand states which consequence
 * it accepts.
 */
enum ReducedMotionStrategy: string
{
    /** Hold the final composed state. Every essential cue must be visible there. */
    case FinalState = 'final_state';

    /** Cross-fade between beats with no movement, at the brand's fast duration. */
    case CrossFadeOnly = 'cross_fade_only';

    /** Present each beat as a static panel in reading order. */
    case StaticSequence = 'static_sequence';
}
