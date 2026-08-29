<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A span of time in whole milliseconds.
 *
 * Integers, not floats: a timeline that is the sum of its parts must stay exactly the sum of
 * its parts, and accumulated floating-point error in cue offsets would make a fifteen-second
 * piece render at 14999.9999ms on one machine and 15000.0001ms on another.
 */
final readonly class Duration implements JsonSerializable
{
    public function __construct(public int $milliseconds)
    {
        if ($milliseconds < 0) {
            throw new InvalidArgumentException('Durations must not be negative.');
        }
    }

    public static function fromSeconds(float $seconds): self
    {
        return new self((int) round($seconds * 1000));
    }

    public function plus(self $other): self
    {
        return new self($this->milliseconds + $other->milliseconds);
    }

    public function isZero(): bool
    {
        return $this->milliseconds === 0;
    }

    public function seconds(): float
    {
        return $this->milliseconds / 1000;
    }

    public function equals(self $other): bool
    {
        return $this->milliseconds === $other->milliseconds;
    }

    public function jsonSerialize(): int
    {
        return $this->milliseconds;
    }
}
