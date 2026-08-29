<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\Severity;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * The composition's pinned brand must be one this library can satisfy.
 *
 * A pin that cannot be met blocks: rendering anyway would silently draw the work against a
 * brand it was never designed for. `RABO_BRAND_DRIFT` defaults to a warning because the
 * glossary's sense of drift also covers material that is merely out of date; an unsatisfiable
 * pin is the stricter case and says so explicitly.
 */
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
                Severity::Error,
            )];
        }
        if (! $brand->version->satisfies($composition->brandVersion)) {
            return [new ValidationIssue(
                IssueCode::BrandDrift,
                'composition.brand_version',
                "Composition '{$composition->id}' pins brand {$composition->brandVersion}, which library version {$brand->version} does not satisfy.",
                Severity::Error,
            )];
        }

        return [];
    }
}
