<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;

/**
 * The accessibility floor the brand commits to.
 *
 * These live in the Brand Library, not in a publishing checklist, so that a composition
 * fails validation at authoring time rather than at review time.
 */
final readonly class AccessibilityRules implements JsonSerializable
{
    public function __construct(
        public float $minContrastNormal = 4.5,
        public float $minContrastLarge = 3.0,
        public bool $requireTextAlternatives = true,
        public bool $requireReducedMotionVariant = true,
        public bool $forbidColorOnlyEncoding = true,
    ) {
        if ($minContrastNormal < 1.0 || $minContrastNormal > 21.0) {
            throw new InvalidArgumentException('Normal-text contrast minimums must be between 1 and 21.');
        }
        if ($minContrastLarge < 1.0 || $minContrastLarge > 21.0) {
            throw new InvalidArgumentException('Large-text contrast minimums must be between 1 and 21.');
        }
        if ($minContrastLarge > $minContrastNormal) {
            throw new InvalidArgumentException('Large text may not demand more contrast than normal text.');
        }
    }

    public function minimumContrastFor(bool $isLargeText): float
    {
        return $isLargeText ? $this->minContrastLarge : $this->minContrastNormal;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'min_contrast_normal' => $this->minContrastNormal,
            'min_contrast_large' => $this->minContrastLarge,
            'require_text_alternatives' => $this->requireTextAlternatives,
            'require_reduced_motion_variant' => $this->requireReducedMotionVariant,
            'forbid_color_only_encoding' => $this->forbidColorOnlyEncoding,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        return new self(
            (float) ($serialized['min_contrast_normal'] ?? 4.5),
            (float) ($serialized['min_contrast_large'] ?? 3.0),
            (bool) ($serialized['require_text_alternatives'] ?? true),
            (bool) ($serialized['require_reduced_motion_variant'] ?? true),
            (bool) ($serialized['forbid_color_only_encoding'] ?? true),
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
