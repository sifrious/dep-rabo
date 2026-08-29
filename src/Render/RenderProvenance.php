<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use DateTimeImmutable;
use JsonSerializable;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Reference\CompositionReferences;

/**
 * Everything needed to answer "where did this file come from".
 *
 * Which composition version, which brand version, which asset bytes, which renderer and
 * version, which request, what came out, and what upstream work it traces to. A render is
 * reproducible when the renderer is deterministic and this record is intact.
 */
final readonly class RenderProvenance implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.render-provenance';

    public const CONTRACT_VERSION = 1;

    /** @param list<ContentDigest> $assets */
    public function __construct(
        public string $compositionId,
        public int $compositionVersion,
        public string $compositionKey,
        public string $scene,
        public string $brandId,
        public string $brandVersion,
        public string $brandKey,
        public array $assets,
        public string $renderer,
        public string $rendererVersion,
        public string $requestDigest,
        public ContentDigest $outputDigest,
        public CompositionReferences $references,
        public DateTimeImmutable $producedAt,
        public bool $deterministic = true,
        /** @var array<string,string> */
        public array $toolVersions = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'composition' => [
                'id' => $this->compositionId,
                'version' => $this->compositionVersion,
                'key' => $this->compositionKey,
                'scene' => $this->scene,
            ],
            'brand' => [
                'id' => $this->brandId,
                'version' => $this->brandVersion,
                'key' => $this->brandKey,
            ],
            'assets' => array_map(strval(...), $this->assets),
            'renderer' => [
                'id' => $this->renderer,
                'version' => $this->rendererVersion,
                'deterministic' => $this->deterministic,
                'tool_versions' => (object) $this->toolVersions,
            ],
            'request_digest' => $this->requestDigest,
            'output_digest' => (string) $this->outputDigest,
            'references' => $this->references->toArray(),
            'produced_at' => $this->producedAt->format('Y-m-d\TH:i:sP'),
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
