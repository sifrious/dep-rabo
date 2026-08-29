<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/** The composition's pinned brand must be one this library can satisfy. */
final readonly class BrandCompatibilityRule implements Rule
{
    public function name(): string
    {
        return 'brand-compatibility';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $composition = $context->composition;
        $brand = $context->brand;

        if ($brand->id !== $composition->brandId) {
            return [new ValidationIssue(
                IssueCode::BrandDrift,
                'composition.brand_id',
                "Composition '{$composition->id}' was authored against brand '{$composition->brandId}' but is being validated against '{$brand->id}'.",
            )];
        }
        if (! $brand->version->satisfies($composition->brandVersion)) {
            return [new ValidationIssue(
                IssueCode::BrandDrift,
                'composition.brand_version',
                "Composition '{$composition->id}' pins brand {$composition->brandVersion}, which library version {$brand->version} does not satisfy.",
            )];
        }

        return [];
    }
}
