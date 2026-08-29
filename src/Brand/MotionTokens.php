<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Motion\ReducedMotionStrategy;

/**
 * The brand's motion vocabulary.
 *
 * Motion is part of the Brand Library rather than a property of one video, so the same
 * durations and easings are available to every composition and a reduced-motion default is
 * declared once for the whole brand.
 */
final readonly class MotionTokens implements JsonSerializable
{
    /** @var array<string,int> */
    public array $durationsMs;

    /** @var array<string,string> */
    public array $easings;

    /**
     * @param  array<string,int>  $durationsMs
     * @param  array<string,string>  $easings
     */
    public function __construct(
        array $durationsMs,
        array $easings,
        public ReducedMotionStrategy $defaultReducedMotionStrategy = ReducedMotionStrategy::FinalState,
    ) {
        foreach ($durationsMs as $name => $ms) {
            if (! is_string($name) || preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
                throw new InvalidArgumentException('Duration token names must be lowercase kebab-case.');
            }
            if (! is_int($ms) || $ms < 0 || $ms > 600000) {
                throw new InvalidArgumentException("Duration token '{$name}' must be between 0 and 600000 milliseconds.");
            }
        }
        foreach ($easings as $name => $curve) {
            if (! is_string($name) || preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
                throw new InvalidArgumentException('Easing token names must be lowercase kebab-case.');
            }
            if (! is_string($curve) || preg_match('/^(linear|steps\(\d+(, ?(start|end|jump-[a-z]+))?\)|cubic-bezier\(\s*-?\d*\.?\d+\s*,\s*-?\d*\.?\d+\s*,\s*-?\d*\.?\d+\s*,\s*-?\d*\.?\d+\s*\))$/', $curve) !== 1) {
                throw new InvalidArgumentException("Easing token '{$name}' must be linear, steps(), or cubic-bezier().");
            }
        }
        if ($durationsMs === [] || $easings === []) {
            throw new InvalidArgumentException('Motion tokens must declare at least one duration and one easing.');
        }
        ksort($durationsMs);
        ksort($easings);
        $this->durationsMs = $durationsMs;
        $this->easings = $easings;
    }

    public function hasDuration(string $name): bool
    {
        return isset($this->durationsMs[$name]);
    }

    public function durationMs(string $name): int
    {
        return $this->durationsMs[$name] ?? throw new UnknownBrandToken("The brand declares no duration token '{$name}'.");
    }

    public function hasEasing(string $name): bool
    {
        return isset($this->easings[$name]);
    }

    public function easing(string $name): string
    {
        return $this->easings[$name] ?? throw new UnknownBrandToken("The brand declares no easing token '{$name}'.");
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'durations_ms' => (object) $this->durationsMs,
            'easings' => (object) $this->easings,
            'default_reduced_motion_strategy' => $this->defaultReducedMotionStrategy->value,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $durations = $serialized['durations_ms'] ?? null;
        $easings = $serialized['easings'] ?? null;
        $strategy = $serialized['default_reduced_motion_strategy'] ?? ReducedMotionStrategy::FinalState->value;
        if (! is_array($durations) || ! is_array($easings) || ! is_string($strategy)) {
            throw new InvalidArgumentException('Serialized motion tokens require durations_ms, easings, and a strategy.');
        }
        $decodedDurations = [];
        foreach ($durations as $name => $ms) {
            $decodedDurations[(string) $name] = is_int($ms) ? $ms : throw new InvalidArgumentException('Duration tokens must be integer milliseconds.');
        }
        $decodedEasings = [];
        foreach ($easings as $name => $curve) {
            $decodedEasings[(string) $name] = is_string($curve) ? $curve : throw new InvalidArgumentException('Easing tokens must be strings.');
        }

        return new self(
            $decodedDurations,
            $decodedEasings,
            ReducedMotionStrategy::tryFrom($strategy) ?? throw new InvalidArgumentException('Serialized motion tokens require a supported reduced-motion strategy.'),
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
