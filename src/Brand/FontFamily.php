<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A declared family, its fallback stack, and its declared advance-width ratios.
 *
 * `advanceRatios` maps a weight onto the average glyph advance as a fraction of the font
 * size. Rabo has no font engine and does not shape text; overflow validation multiplies
 * this brand-declared ratio by the character count. It is an estimate the BRAND owns, and
 * is deliberately conservative rather than accurate — see docs/assumptions.md.
 */
final readonly class FontFamily implements JsonSerializable
{
    /** @var list<string> */
    public array $fallbacks;

    /** @var list<int> */
    public array $weights;

    /** @var array<int,float> */
    public array $advanceRatios;

    /**
     * @param  list<string>  $fallbacks
     * @param  list<int>  $weights
     * @param  array<int,float>  $advanceRatios
     */
    public function __construct(
        public string $name,
        array $fallbacks,
        array $weights,
        array $advanceRatios,
    ) {
        if (trim($name) !== $name || $name === '') {
            throw new InvalidArgumentException('Font family names must be non-empty and trimmed.');
        }
        foreach ($fallbacks as $fallback) {
            if (! is_string($fallback) || trim($fallback) === '') {
                throw new InvalidArgumentException("Font family '{$name}' has an empty fallback entry.");
            }
        }
        if ($weights === []) {
            throw new InvalidArgumentException("Font family '{$name}' must declare at least one weight.");
        }
        sort($weights);
        foreach ($weights as $weight) {
            if (! is_int($weight) || $weight < 1 || $weight > 1000) {
                throw new InvalidArgumentException("Font family '{$name}' declares an out-of-range weight.");
            }
            if (! isset($advanceRatios[$weight])) {
                throw new InvalidArgumentException("Font family '{$name}' declares weight {$weight} without an advance ratio.");
            }
        }
        foreach ($advanceRatios as $weight => $ratio) {
            if (! is_int($weight) || ! is_float($ratio) || $ratio <= 0.0 || $ratio > 2.0) {
                throw new InvalidArgumentException("Font family '{$name}' has an invalid advance ratio.");
            }
        }
        ksort($advanceRatios);

        $this->fallbacks = array_values($fallbacks);
        $this->weights = array_values($weights);
        $this->advanceRatios = $advanceRatios;
    }

    public function supportsWeight(int $weight): bool
    {
        return in_array($weight, $this->weights, true);
    }

    public function advanceRatio(int $weight): float
    {
        return $this->advanceRatios[$weight]
            ?? throw new UnknownBrandToken("Font family '{$this->name}' declares no weight {$weight}.");
    }

    /** The full CSS font stack, family first. */
    public function stack(): string
    {
        return implode(', ', array_map(
            static fn (string $family): string => str_contains($family, ' ') ? "'{$family}'" : $family,
            [$this->name, ...$this->fallbacks],
        ));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $ratios = [];
        foreach ($this->advanceRatios as $weight => $ratio) {
            $ratios[(string) $weight] = $ratio;
        }

        return [
            'name' => $this->name,
            'fallbacks' => $this->fallbacks,
            'weights' => $this->weights,
            'advance_ratios' => (object) $ratios,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $name = $serialized['name'] ?? null;
        $fallbacks = $serialized['fallbacks'] ?? [];
        $weights = $serialized['weights'] ?? null;
        $ratios = $serialized['advance_ratios'] ?? null;
        if (! is_string($name) || ! is_array($fallbacks) || ! is_array($weights) || ! is_array($ratios)) {
            throw new InvalidArgumentException('Serialized font families require name, fallbacks, weights, and advance ratios.');
        }
        $decodedRatios = [];
        foreach ($ratios as $weight => $ratio) {
            $decodedRatios[(int) $weight] = (float) $ratio;
        }

        return new self($name, array_values($fallbacks), array_map(intval(...), array_values($weights)), $decodedRatios);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
