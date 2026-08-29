<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Asset;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Who may use an asset, and on what terms.
 *
 * Rights travel with the asset rather than with a database row, so an exported Brand
 * Library stays answerable about licensing. Rabo does not adjudicate rights; it refuses to
 * lose them.
 */
final readonly class AssetRights implements JsonSerializable
{
    public function __construct(
        public string $holder,
        public string $license,
        public bool $attributionRequired = false,
        public ?string $terms = null,
    ) {
        if (trim($holder) === '') {
            throw new InvalidArgumentException('Asset rights must name a holder.');
        }
        if (trim($license) === '') {
            throw new InvalidArgumentException('Asset rights must name a license.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'holder' => $this->holder,
            'license' => $this->license,
            'attribution_required' => $this->attributionRequired,
            'terms' => $this->terms,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $holder = $serialized['holder'] ?? null;
        $license = $serialized['license'] ?? null;
        $attributionRequired = $serialized['attribution_required'] ?? false;
        $terms = $serialized['terms'] ?? null;
        if (! is_string($holder) || ! is_string($license) || ! is_bool($attributionRequired)) {
            throw new InvalidArgumentException('Serialized asset rights require holder, license, and attribution_required.');
        }
        if ($terms !== null && ! is_string($terms)) {
            throw new InvalidArgumentException('Serialized asset rights terms must be a string or null.');
        }

        return new self($holder, $license, $attributionRequired, $terms);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
