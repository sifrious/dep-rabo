<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

/** How a cue moves through its duration. The curve itself is a brand motion token. */
enum Easing: string
{
    case Linear = 'linear';
    case Quiet = 'quiet';
    case Out = 'out';
}
