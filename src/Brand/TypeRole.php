<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;

/** A semantic type role: the brand's answer to "what is a headline". */
final readonly class TypeRole implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $family,
        public int $weight,
        public int $sizePx,
        public float $lineHeight,
        public float $tracking = 0.0,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException('Type role names must be lowercase kebab-case identifiers.');
        }
        if ($sizePx <= 0 || $sizePx > 1024) {
            throw new InvalidArgumentException("Type role '{$name}' must declare a size between 1 and 1024 pixels.");
        }
        if ($lineHeight <= 0.0 || $lineHeight > 4.0) {
            throw new InvalidArgumentException("Type role '{$name}' must declare a line height between 0 and 4.");
        }
        if ($tracking < -0.5 || $tracking > 0.5) {
            throw new InvalidArgumentException("Type role '{$name}' declares tracking outside -0.5em to 0.5em.");
        }
    }

    /** WCAG large text: 24px, or 18.66px at weight 700 or above. */
    public function isLargeText(): bool
    {
        return $this->sizePx >= 24 || ($this->weight >= 700 && $this->sizePx >= 19);
    }

    public function lineHeightPx(): float
    {
        return $this->sizePx * $this->lineHeight;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'family' => $this->family,
            'weight' => $this->weight,
            'size_px' => $this->sizePx,
            'line_height' => $this->lineHeight,
            'tracking' => $this->tracking,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $name = $serialized['name'] ?? null;
        $family = $serialized['family'] ?? null;
        $weight = $serialized['weight'] ?? null;
        $sizePx = $serialized['size_px'] ?? null;
        $lineHeight = $serialized['line_height'] ?? null;
        $tracking = $serialized['tracking'] ?? 0.0;
        if (! is_string($name) || ! is_string($family) || ! is_int($weight) || ! is_int($sizePx)) {
            throw new InvalidArgumentException('Serialized type roles require name, family, weight, and size_px.');
        }
        if (! is_int($lineHeight) && ! is_float($lineHeight)) {
            throw new InvalidArgumentException('Serialized type roles require a numeric line_height.');
        }

        return new self($name, $family, $weight, $sizePx, (float) $lineHeight, (float) $tracking);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
