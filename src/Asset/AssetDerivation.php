<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Asset;

use InvalidArgumentException;
use JsonSerializable;

/**
 * How a derived asset came from its source.
 *
 * The derived asset keeps its own identity — different bytes are a different asset — while
 * remaining traceable to what it was made from and by what transform.
 */
final readonly class AssetDerivation implements JsonSerializable
{
    public function __construct(
        public ContentDigest $source,
        public string $transform,
        public ?string $tool = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $transform) !== 1) {
            throw new InvalidArgumentException('Derivation transforms must be lowercase kebab-case identifiers.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source' => (string) $this->source,
            'transform' => $this->transform,
            'tool' => $this->tool,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $source = $serialized['source'] ?? null;
        $transform = $serialized['transform'] ?? null;
        $tool = $serialized['tool'] ?? null;
        if (! is_string($source) || ! is_string($transform)) {
            throw new InvalidArgumentException('Serialized derivations require source and transform.');
        }
        if ($tool !== null && ! is_string($tool)) {
            throw new InvalidArgumentException('Serialized derivation tools must be a string or null.');
        }

        return new self(ContentDigest::parse($source), $transform, $tool);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
