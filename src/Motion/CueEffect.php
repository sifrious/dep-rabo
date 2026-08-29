<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

/**
 * What a cue does to its node.
 *
 * A small set on purpose. These are the moves a diagram needs to explain itself in sequence;
 * anything richer belongs to a motion design tool, and Rabo would only be pretending to own
 * it.
 */
enum CueEffect: string
{
    /** Bring the node in. It stays visible afterwards. */
    case Reveal = 'reveal';

    /** Draw attention to a node that is already present. */
    case Emphasise = 'emphasise';

    /** Take the node away. Anything essential must not use this. */
    case Dismiss = 'dismiss';

    /** Whether the node is still on screen once the cue has run. */
    public function leavesNodeVisible(): bool
    {
        return $this !== self::Dismiss;
    }
}
