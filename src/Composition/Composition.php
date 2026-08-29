<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Brand\BrandVersion;
use Sifrious\Rabo\Reference\CompositionReferences;

/**
 * The portable envelope around one piece of visual work.
 *
 * It pins the brand it was authored against, holds the canonical scene and the variants
 * derived from it, and carries the references that explain why it exists and what supports
 * it. This — not a rendered file — is the thing that is edited, reviewed, and versioned.
 *
 * A render artifact is an output of a composition. It never replaces one.
 */
final readonly class Composition implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.composition';

    public const CONTRACT_VERSION = 1;

    /** @var array<string,VariantSpec> */
    public array $variants;

    /** @param list<VariantSpec> $variants */
    public function __construct(
        public string $id,
        public int $version,
        public string $brandId,
        public BrandVersion $brandVersion,
        public Scene $scene,
        array $variants = [],
        public CompositionReferences $references = new CompositionReferences(),
        public ?string $title = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Composition identifiers must be lowercase kebab-case.');
        }
        if ($version < 1) {
            throw new InvalidArgumentException('Composition versions start at 1.');
        }
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $brandId) !== 1) {
            throw new InvalidArgumentException('Composition brand identifiers must be lowercase kebab-case.');
        }

        $indexed = [];
        foreach ($variants as $variant) {
            if (! $variant instanceof VariantSpec) {
                throw new InvalidArgumentException('Compositions accept VariantSpec values only.');
            }
            if (isset($indexed[$variant->id])) {
                throw new InvalidArgumentException("Variant '{$variant->id}' is declared twice.");
            }
            foreach ([...array_keys($variant->stackDirections), ...array_keys($variant->stackAlignments)] as $nodeId) {
                if ($scene->findNode(new NodeId((string) $nodeId)) === null) {
                    throw new InvalidArgumentException("Variant '{$variant->id}' overrides unknown node '{$nodeId}'.");
                }
            }
            $indexed[$variant->id] = $variant;
        }
        ksort($indexed);
        $this->variants = $indexed;
    }

    /** True when this composition may be rendered by the supplied library. */
    public function acceptsBrand(BrandLibrary $brand): bool
    {
        return $brand->id === $this->brandId && $brand->version->satisfies($this->brandVersion);
    }

    public function variant(string $id): VariantSpec
    {
        return $this->variants[$id] ?? throw new InvalidArgumentException("Composition '{$this->id}' declares no variant '{$id}'.");
    }

    /** The derived scene for a variant. The canonical scene is never modified. */
    public function variantScene(string $id): Scene
    {
        return $this->scene->deriveVariant($this->variant($id));
    }

    /** Canonical scene first, then every variant in identifier order. */
    /** @return array<string,Scene> */
    public function allScenes(): array
    {
        $scenes = ['source' => $this->scene];
        foreach ($this->variants as $variantId => $variant) {
            $scenes[$variantId] = $this->scene->deriveVariant($variant);
        }

        return $scenes;
    }

    /** A stable identity for this exact composition content. */
    public function key(): string
    {
        return hash('sha256', $this->canonical());
    }

    public function canonical(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function equals(self $other): bool
    {
        return $this->canonical() === $other->canonical();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'id' => $this->id,
            'version' => $this->version,
            'title' => $this->title,
            'brand_id' => $this->brandId,
            'brand_version' => (string) $this->brandVersion,
            'scene' => $this->scene->toArray(),
            'variants' => array_values(array_map(static fn (VariantSpec $v): array => $v->toArray(), $this->variants)),
            'references' => $this->references->toArray(),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo composition contract.');
        }
        $id = $serialized['id'] ?? null;
        $version = $serialized['version'] ?? null;
        $title = $serialized['title'] ?? null;
        $brandId = $serialized['brand_id'] ?? null;
        $brandVersion = $serialized['brand_version'] ?? null;
        $scene = $serialized['scene'] ?? null;
        $variants = $serialized['variants'] ?? [];
        $references = $serialized['references'] ?? null;
        if (! is_string($id) || ! is_int($version) || ! is_string($brandId) || ! is_string($brandVersion) || ! is_array($scene) || ! is_array($variants)) {
            throw new InvalidArgumentException('Serialized compositions require id, version, brand_id, brand_version, scene, and variants.');
        }
        if ($title !== null && ! is_string($title)) {
            throw new InvalidArgumentException('Serialized composition titles must be a string or null.');
        }

        return new self(
            $id,
            $version,
            $brandId,
            BrandVersion::parse($brandVersion),
            Scene::fromArray($scene),
            array_map(static function (mixed $v): VariantSpec {
                if (! is_array($v)) {
                    throw new InvalidArgumentException('Serialized variants must be objects.');
                }

                return VariantSpec::fromArray($v);
            }, array_values($variants)),
            is_array($references) ? CompositionReferences::fromArray($references) : new CompositionReferences(),
            $title,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
