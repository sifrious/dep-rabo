<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Asset;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Composition\Dimensions;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * Content-addressed source material.
 *
 * The digest is the identity. `label` is a human convenience and carries no authority: two
 * assets with the same label and different bytes are two different assets, and the same
 * bytes under two labels are one asset.
 */
final readonly class Asset implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.asset';

    public const CONTRACT_VERSION = 1;

    public function __construct(
        public ContentDigest $digest,
        public string $mediaType,
        public int $byteLength,
        public AssetRights $rights,
        public ?Dimensions $dimensions = null,
        public ?AssetDerivation $derivation = null,
        public ?CrossPackageReference $provenance = null,
        public ?string $label = null,
    ) {
        if (preg_match('~^[a-z0-9][a-z0-9!#$&^_.+-]*/[a-z0-9][a-z0-9!#$&^_.+-]*$~', $mediaType) !== 1) {
            throw new InvalidArgumentException('Assets must declare a lowercase IANA media type.');
        }
        if ($byteLength < 0) {
            throw new InvalidArgumentException('Asset byte lengths must not be negative.');
        }
        if ($derivation !== null && $derivation->source->equals($digest)) {
            throw new InvalidArgumentException('An asset cannot be derived from itself.');
        }
    }

    public function isDerived(): bool
    {
        return $this->derivation !== null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'digest' => (string) $this->digest,
            'media_type' => $this->mediaType,
            'byte_length' => $this->byteLength,
            'rights' => $this->rights->toArray(),
            'dimensions' => $this->dimensions?->toArray(),
            'derivation' => $this->derivation?->toArray(),
            'provenance' => $this->provenance?->toArray(),
            'label' => $this->label,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo asset contract.');
        }
        $digest = $serialized['digest'] ?? null;
        $mediaType = $serialized['media_type'] ?? null;
        $byteLength = $serialized['byte_length'] ?? null;
        $rights = $serialized['rights'] ?? null;
        $dimensions = $serialized['dimensions'] ?? null;
        $derivation = $serialized['derivation'] ?? null;
        $provenance = $serialized['provenance'] ?? null;
        $label = $serialized['label'] ?? null;
        if (! is_string($digest) || ! is_string($mediaType) || ! is_int($byteLength) || ! is_array($rights)) {
            throw new InvalidArgumentException('Serialized assets require digest, media_type, byte_length, and rights.');
        }
        if ($label !== null && ! is_string($label)) {
            throw new InvalidArgumentException('Serialized asset labels must be a string or null.');
        }

        return new self(
            ContentDigest::parse($digest),
            $mediaType,
            $byteLength,
            AssetRights::fromArray($rights),
            is_array($dimensions) ? Dimensions::fromArray($dimensions) : null,
            is_array($derivation) ? AssetDerivation::fromArray($derivation) : null,
            is_array($provenance) ? CrossPackageReference::fromArray($provenance) : null,
            $label,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
