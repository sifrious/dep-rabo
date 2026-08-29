<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Reference\ReferenceRole;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * A composition must be able to say why it exists and what supports it.
 *
 * Missing optional references are silent; missing required ones are errors. Rabo never
 * invents a reference to fill the gap, and never treats absence as "probably fine".
 */
final readonly class ReferenceRule implements Rule
{
    public function name(): string
    {
        return 'reference';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $issues = [];
        foreach ($context->composition->references->missingRequired() as $role) {
            $issues[] = new ValidationIssue(
                IssueCode::ReferenceRequiredMissing,
                'references.'.$role->value,
                sprintf(
                    "Composition '%s' carries no %s reference, which must be owned by one of: %s.",
                    $context->composition->id, $role->value, implode(', ', $role->owners()),
                ),
            );
        }

        return $issues;
    }
}
