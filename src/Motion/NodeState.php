<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

/** What a node looks like at one instant: the sampled equivalent of the CSS keyframes. */
final readonly class NodeState
{
    public function __construct(
        public float $opacity,
        public float $translateY = 0.0,
    ) {}

    public function isDefault(): bool
    {
        return abs($this->opacity - 1.0) < 0.0005 && abs($this->translateY) < 0.005;
    }
}
