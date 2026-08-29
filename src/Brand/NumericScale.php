<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;

/** A named set of numeric brand decisions: spacing steps, radii, stroke widths. */
final readonly class NumericScale implements JsonSerializable
{
    /** @var array<string,float> */
    public array $steps;

    /** @param array<string,float|int> $steps */
    public function __construct(public string $name, array $steps)
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException('Scale names must be lowercase kebab-case identifiers.');
        }
        $normalised = [];
        foreach ($steps as $step => $value) {
            if (! is_string($step) || preg_match('/^[a-z0-9][a-z0-9-]*$/', $step) !== 1) {
                throw new InvalidArgumentException("Scale '{$name}' has a step that is not a lowercase identifier.");
            }
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException("Scale '{$name}' step '{$step}' must be numeric.");
            }
            if ($value < 0) {
                throw new InvalidArgumentException("Scale '{$name}' step '{$step}' must not be negative.");
            }
            $normalised[$step] = (float) $value;
        }
        if ($normalised === []) {
            throw new InvalidArgumentException("Scale '{$name}' must declare at least one step.");
        }
        ksort($normalised);
        $this->steps = $normalised;
    }

    public function has(string $step): bool
    {
        return isset($this->steps[$step]);
    }

    public function step(string $step): float
    {
        return $this->steps[$step] ?? throw new UnknownBrandToken("Scale '{$this->name}' declares no step '{$step}'.");
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'steps' => (object) $this->steps];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $name = $serialized['name'] ?? null;
        $steps = $serialized['steps'] ?? null;
        if (! is_string($name) || ! is_array($steps)) {
            throw new InvalidArgumentException('Serialized scales require a name and steps.');
        }

        return new self($name, $steps);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
