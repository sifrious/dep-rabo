<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

enum Alignment: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';

    /** The offset of a child of $childExtent inside a track of $trackExtent. */
    public function offsetWithin(float $trackExtent, float $childExtent): float
    {
        return match ($this) {
            self::Start => 0.0,
            self::Center => ($trackExtent - $childExtent) / 2,
            self::End => $trackExtent - $childExtent,
        };
    }
}
