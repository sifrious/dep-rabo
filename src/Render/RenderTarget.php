<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Composition\Dimensions;

/**
 * The output being asked for.
 *
 * `reducedMotion` is part of the target rather than a post-processing step: a reduced-motion
 * rendering is a different artifact with its own identity and its own provenance, not the
 * same artifact with animation stripped afterwards.
 */
final readonly class RenderTarget implements JsonSerializable
{
    public function __construct(
        public RenderFormat $format,
        public Dimensions $dimensions,
        public ?int $framesPerSecond = null,
        public bool $reducedMotion = false,
    ) {
        if ($format->isTemporal() && $framesPerSecond !== null && ($framesPerSecond < 1 || $framesPerSecond > 120)) {
            throw new InvalidArgumentException('Frame rates must be between 1 and 120.');
        }
        if (! $format->isTemporal() && $framesPerSecond !== null) {
            throw new InvalidArgumentException('A still target may not declare a frame rate.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'format' => $this->format->value,
            'dimensions' => $this->dimensions->toArray(),
            'frames_per_second' => $this->framesPerSecond,
            'reduced_motion' => $this->reducedMotion,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $format = $serialized['format'] ?? null;
        $dimensions = $serialized['dimensions'] ?? null;
        $fps = $serialized['frames_per_second'] ?? null;
        $reduced = $serialized['reduced_motion'] ?? false;
        if (! is_string($format) || ! is_array($dimensions) || ! is_bool($reduced)) {
            throw new InvalidArgumentException('Serialized render targets require format, dimensions, and reduced_motion.');
        }
        if ($fps !== null && ! is_int($fps)) {
            throw new InvalidArgumentException('Serialized frame rates must be an integer or null.');
        }

        return new self(
            RenderFormat::tryFrom($format) ?? throw new InvalidArgumentException("Rabo has no render format '{$format}'."),
            Dimensions::fromArray($dimensions),
            $fps,
            $reduced,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
