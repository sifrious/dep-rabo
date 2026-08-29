<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;

/** Integer pixel dimensions. Integers keep every downstream layout calculation deterministic. */
final readonly class Dimensions implements JsonSerializable
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Dimensions must be positive.');
        }
        if ($width > 16384 || $height > 16384) {
            throw new InvalidArgumentException('Dimensions must not exceed 16384 pixels on a side.');
        }
    }

    public function aspectRatio(): string
    {
        $divisor = self::greatestCommonDivisor($this->width, $this->height);

        return ($this->width / $divisor).':'.($this->height / $divisor);
    }

    public function isPortrait(): bool
    {
        return $this->height > $this->width;
    }

    public function equals(self $other): bool
    {
        return $this->width === $other->width && $this->height === $other->height;
    }

    /** @return array{width:int,height:int} */
    public function toArray(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $width = $serialized['width'] ?? null;
        $height = $serialized['height'] ?? null;
        if (! is_int($width) || ! is_int($height)) {
            throw new InvalidArgumentException('Serialized dimensions require integer width and height.');
        }

        return new self($width, $height);
    }

    /** @return array{width:int,height:int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function greatestCommonDivisor(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
