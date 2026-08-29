<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * Text must clear the brand's contrast floor against whatever it sits on.
 *
 * The threshold follows WCAG's large-text allowance, which the type role decides: a 50px
 * headline is held to a lower ratio than 16px body copy, because it genuinely is easier to
 * read. Both numbers come from the brand, not from this rule.
 */
final readonly class ContrastRule implements Rule
{
    public function name(): string
    {
        return 'contrast';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $brand = $context->brand;
        $issues = [];

        foreach ($context->scene->nodes() as $node) {
            if (! $node instanceof TextNode) {
                continue;
            }
            $inkRole = $node->style()->text;
            $backgroundRole = $context->backgroundRoleFor($node->id());
            if ($inkRole === null || $backgroundRole === null) {
                continue;
            }
            if (! $brand->colors->hasRole($inkRole) || ! $brand->colors->hasRole($backgroundRole)) {
                continue; // BrandTokenRule owns unresolvable tokens.
            }

            $typeRole = $node->style()->typeRole;
            $isLarge = $typeRole !== null && $brand->typography->hasRole($typeRole)
                && $brand->typography->role($typeRole)->isLargeText();
            $required = $brand->accessibility->minimumContrastFor($isLarge);
            $actual = $brand->colors->resolveRole($inkRole)->contrastAgainst($brand->colors->resolveRole($backgroundRole));

            if ($actual + 0.005 < $required) {
                $issues[] = new ValidationIssue(
                    IssueCode::ContrastInsufficient,
                    (string) $node->id(),
                    sprintf(
                        "Text '%s' renders %s on %s at %.2f:1, below the %.1f:1 this brand requires for %s text.",
                        $node->id(), $inkRole, $backgroundRole, $actual, $required, $isLarge ? 'large' : 'normal',
                    ),
                );
            }
        }

        return $issues;
    }
}
