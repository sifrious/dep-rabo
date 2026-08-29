<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Composition;
use Sifrious\Rabo\Composition\Scene;

/**
 * A complete, self-contained request to render one scene.
 *
 * It carries the composition and the brand rather than identifiers for them, so a renderer
 * in another process needs no access to the application that authored the work. Two requests
 * with the same digest ask for exactly the same thing.
 */
final readonly class RenderRequest implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.render-request';

    public const CONTRACT_VERSION = 1;

    public function __construct(
        public Composition $composition,
        public BrandLibrary $brand,
        public string $scene,
        public RenderTarget $target,
    ) {
        if (! $composition->acceptsBrand($brand)) {
            throw new InvalidArgumentException(
                "Composition '{$composition->id}' pins brand '{$composition->brandId}' {$composition->brandVersion}, which '{$brand->id}' {$brand->version} does not satisfy."
            );
        }
        if (! array_key_exists($scene, $composition->allScenes())) {
            throw new InvalidArgumentException("Composition '{$composition->id}' has no scene '{$scene}'.");
        }
    }

    public function scene(): Scene
    {
        return $this->composition->allScenes()[$this->scene];
    }

    /** The identity of this request, used to prove that two renders were asked the same thing. */
    public function digest(): string
    {
        return hash('sha256', json_encode([
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'composition' => $this->composition->key(),
            'brand' => $this->brand->key(),
            'scene' => $this->scene,
            'target' => $this->target->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'composition' => $this->composition->toArray(),
            'brand' => $this->brand->toArray(),
            'scene' => $this->scene,
            'target' => $this->target->toArray(),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo render request contract.');
        }
        $composition = $serialized['composition'] ?? null;
        $brand = $serialized['brand'] ?? null;
        $scene = $serialized['scene'] ?? null;
        $target = $serialized['target'] ?? null;
        if (! is_array($composition) || ! is_array($brand) || ! is_string($scene) || ! is_array($target)) {
            throw new InvalidArgumentException('Serialized render requests require composition, brand, scene, and target.');
        }

        return new self(Composition::fromArray($composition), BrandLibrary::fromArray($brand), $scene, RenderTarget::fromArray($target));
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
