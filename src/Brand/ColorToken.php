<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;

/**
 * One named colour decision.
 *
 * sRGB hex is the canonical, portable form: every renderer can consume it and contrast
 * arithmetic over it is exact. `display` carries an authoring-space string (oklch, lab,
 * whatever the brand was drawn in) for round-tripping back to design tools; Rabo never
 * computes with it.
 */
final readonly class ColorToken implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $hex,
        public ?string $display = null,
        public ?string $description = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*(\.[a-z0-9]+(-[a-z0-9]+)*)*$/', $name) !== 1) {
            throw new InvalidArgumentException('Colour token names must be lowercase dotted identifiers.');
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $hex) !== 1) {
            throw new InvalidArgumentException("Colour token '{$name}' must carry a six-digit lowercase sRGB hex value.");
        }
    }

    /** @return array{r:int,g:int,b:int} */
    public function rgb(): array
    {
        return [
            'r' => (int) hexdec(substr($this->hex, 1, 2)),
            'g' => (int) hexdec(substr($this->hex, 3, 2)),
            'b' => (int) hexdec(substr($this->hex, 5, 2)),
        ];
    }

    /** WCAG 2.2 relative luminance. */
    public function relativeLuminance(): float
    {
        $channel = static function (int $value): float {
            $srgb = $value / 255;

            return $srgb <= 0.04045 ? $srgb / 12.92 : (($srgb + 0.055) / 1.055) ** 2.4;
        };

        ['r' => $r, 'g' => $g, 'b' => $b] = $this->rgb();

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /** WCAG 2.2 contrast ratio, 1.0 to 21.0. */
    public function contrastAgainst(self $other): float
    {
        $lighter = max($this->relativeLuminance(), $other->relativeLuminance());
        $darker = min($this->relativeLuminance(), $other->relativeLuminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'hex' => $this->hex,
            'display' => $this->display,
            'description' => $this->description,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $name = $serialized['name'] ?? null;
        $hex = $serialized['hex'] ?? null;
        $display = $serialized['display'] ?? null;
        $description = $serialized['description'] ?? null;
        if (! is_string($name) || ! is_string($hex)) {
            throw new InvalidArgumentException('Serialized colour tokens require string name and hex values.');
        }
        if ($display !== null && ! is_string($display)) {
            throw new InvalidArgumentException('Serialized colour token display values must be strings or null.');
        }
        if ($description !== null && ! is_string($description)) {
            throw new InvalidArgumentException('Serialized colour token descriptions must be strings or null.');
        }

        return new self($name, $hex, $display, $description);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
