<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Asset\ContentDigest;

/**
 * One font file belonging to a family.
 *
 * `declaredFamily` is the name the bytes call themselves, recorded only when it differs from the
 * family's own name. That happens more often than it should: the Space Grotesk variable file
 * declares "Space Grotesk Light", after its default axis instance, so a rasterizer matching by
 * file-declared name would never find "Space Grotesk". This is a property of the bytes rather
 * than a brand decision, which is why it lives here and not on the family.
 *
 * `licence` names the licence text that must travel with the bytes. The OFL requires it, and a
 * digest recorded only as prose inside `AssetRights.terms` is invisible to `assets()`, so the
 * licence never reached the render provenance that is supposed to list everything an artifact
 * depends on. A structured reference is the difference between claiming the licence ships and
 * being able to show that it does.
 */
final readonly class FontFile implements JsonSerializable
{
    public function __construct(
        public ContentDigest $digest,
        public FontFormat $format,
        public ?string $declaredFamily = null,
        public ?ContentDigest $licence = null,
    ) {
        if ($declaredFamily !== null && ! FontFamily::isSafeFamilyName($declaredFamily)) {
            throw new InvalidArgumentException('A declared font family must be a plain, non-empty typeface name.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'digest' => (string) $this->digest,
            'format' => $this->format->value,
            'declared_family' => $this->declaredFamily,
            'licence' => $this->licence === null ? null : (string) $this->licence,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $digest = $serialized['digest'] ?? null;
        $format = $serialized['format'] ?? null;
        $declaredFamily = $serialized['declared_family'] ?? null;
        $licence = $serialized['licence'] ?? null;
        if ($licence !== null && ! is_string($licence)) {
            throw new InvalidArgumentException('Serialized font licences must be a digest string or null.');
        }
        if (! is_string($digest) || ! is_string($format)) {
            throw new InvalidArgumentException('Serialized font files require a digest and a format.');
        }
        if ($declaredFamily !== null && ! is_string($declaredFamily)) {
            throw new InvalidArgumentException('Serialized declared font families must be a string or null.');
        }

        return new self(
            ContentDigest::parse($digest),
            FontFormat::tryFrom($format) ?? throw new InvalidArgumentException("Rabo has no font format '{$format}'."),
            $declaredFamily,
            $licence === null ? null : ContentDigest::parse($licence),
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
