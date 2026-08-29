<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Asset\ContentDigest;

/**
 * A logo or wordmark, plus the treatment rules that protect it.
 *
 * `clearspaceRatio` is a multiple of the mark's rendered height that must stay empty on
 * every side. `minWidthPx` is the smallest width at which the mark stays legible. Both are
 * enforced by validation rather than left to a designer's memory.
 */
final readonly class Mark implements JsonSerializable
{
    /** @var array<string,ContentDigest> */
    public array $variants;

    /** @param array<string,ContentDigest> $variants */
    public function __construct(
        public string $id,
        public string $label,
        public ContentDigest $asset,
        public int $minWidthPx,
        public float $clearspaceRatio,
        array $variants = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Mark identifiers must be lowercase kebab-case.');
        }
        if ($minWidthPx <= 0) {
            throw new InvalidArgumentException("Mark '{$id}' must declare a positive minimum width.");
        }
        if ($clearspaceRatio < 0.0 || $clearspaceRatio > 4.0) {
            throw new InvalidArgumentException("Mark '{$id}' must declare a clearspace ratio between 0 and 4.");
        }
        foreach ($variants as $variant => $digest) {
            if (! is_string($variant) || preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $variant) !== 1) {
                throw new InvalidArgumentException("Mark '{$id}' has a variant name that is not lowercase kebab-case.");
            }
            if (! $digest instanceof ContentDigest) {
                throw new InvalidArgumentException("Mark '{$id}' variant '{$variant}' must be a ContentDigest.");
            }
        }
        ksort($variants);
        $this->variants = $variants;
    }

    public function variant(string $name): ContentDigest
    {
        return $this->variants[$name] ?? throw new UnknownBrandToken("Mark '{$this->id}' declares no variant '{$name}'.");
    }

    /** Every asset digest this mark depends on, source first. */
    /** @return list<ContentDigest> */
    public function assets(): array
    {
        return [$this->asset, ...array_values($this->variants)];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $variants = [];
        foreach ($this->variants as $name => $digest) {
            $variants[$name] = (string) $digest;
        }

        return [
            'id' => $this->id,
            'label' => $this->label,
            'asset' => (string) $this->asset,
            'min_width_px' => $this->minWidthPx,
            'clearspace_ratio' => $this->clearspaceRatio,
            'variants' => (object) $variants,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $label = $serialized['label'] ?? null;
        $asset = $serialized['asset'] ?? null;
        $minWidthPx = $serialized['min_width_px'] ?? null;
        $clearspaceRatio = $serialized['clearspace_ratio'] ?? null;
        $variants = $serialized['variants'] ?? [];
        if (! is_string($id) || ! is_string($label) || ! is_string($asset) || ! is_int($minWidthPx)) {
            throw new InvalidArgumentException('Serialized marks require id, label, asset, and min_width_px.');
        }
        if (! is_int($clearspaceRatio) && ! is_float($clearspaceRatio)) {
            throw new InvalidArgumentException('Serialized marks require a numeric clearspace_ratio.');
        }
        if (! is_array($variants)) {
            throw new InvalidArgumentException('Serialized mark variants must be an object.');
        }
        $decoded = [];
        foreach ($variants as $name => $digest) {
            if (! is_string($digest)) {
                throw new InvalidArgumentException('Serialized mark variants must be digest strings.');
            }
            $decoded[(string) $name] = ContentDigest::parse($digest);
        }

        return new self($id, $label, ContentDigest::parse($asset), $minWidthPx, (float) $clearspaceRatio, $decoded);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
