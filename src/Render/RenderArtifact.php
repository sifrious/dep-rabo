<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use JsonSerializable;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Composition\Dimensions;

/**
 * Produced output.
 *
 * An artifact is a result of a composition, never a replacement for one. Editing continues
 * to happen against the scene; this is what gets published.
 */
final readonly class RenderArtifact implements JsonSerializable
{
    public ContentDigest $digest;

    public function __construct(
        public string $bytes,
        public string $mediaType,
        public Dimensions $dimensions,
        public ?int $durationMs = null,
        public ?string $filename = null,
    ) {
        $this->digest = ContentDigest::ofBytes($bytes);
    }

    public function byteLength(): int
    {
        return strlen($this->bytes);
    }

    /** Everything but the bytes, for logs and provenance. */
    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'digest' => (string) $this->digest,
            'media_type' => $this->mediaType,
            'byte_length' => $this->byteLength(),
            'dimensions' => $this->dimensions->toArray(),
            'duration_ms' => $this->durationMs,
            'filename' => $this->filename,
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
