<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use JsonSerializable;

/**
 * What a renderer can actually do.
 *
 * Capability is declared and negotiated before work starts, so an unsupported request is a
 * deterministic refusal rather than a half-produced artifact.
 */
final readonly class RenderCapability implements JsonSerializable
{
    /** @var list<RenderFormat> */
    public array $formats;

    /** @param list<RenderFormat> $formats */
    public function __construct(
        public string $renderer,
        public string $version,
        array $formats,
        public int $maxWidth = 16384,
        public int $maxHeight = 16384,
        public bool $deterministic = true,
        public ?int $maxDurationMs = null,
    ) {
        $this->formats = array_values($formats);
    }

    public function supports(RenderTarget $target): bool
    {
        return in_array($target->format, $this->formats, true)
            && $target->dimensions->width <= $this->maxWidth
            && $target->dimensions->height <= $this->maxHeight;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'renderer' => $this->renderer,
            'version' => $this->version,
            'formats' => array_map(static fn (RenderFormat $f): string => $f->value, $this->formats),
            'max_width' => $this->maxWidth,
            'max_height' => $this->maxHeight,
            'deterministic' => $this->deterministic,
            'max_duration_ms' => $this->maxDurationMs,
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
