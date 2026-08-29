<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Brand\TextFlow;
use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * Text must plausibly fit the box it was given.
 *
 * Rabo has no font engine, so this is an estimate built from advance-width ratios the brand
 * declares — see docs/assumptions.md. It is deliberately conservative rather than precise:
 * catching most overflows cheaply and deterministically is worth more here than being exactly
 * right about a few.
 *
 * `maxLines` is an allowance, not a requirement. Text that fits on one line inside a two-line
 * box is correct, so height is measured against the lines actually needed.
 *
 * The wrapping is the renderer's own, via TextFlow, so a caption cannot pass here and then
 * lose its last word when drawn.
 */
final readonly class TextOverflowRule implements Rule
{
    public function name(): string
    {
        return 'text-overflow';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $brand = $context->brand;
        $flow = new TextFlow($brand->typography);
        $issues = [];

        foreach ($context->scene->nodes() as $node) {
            if (! $node instanceof TextNode) {
                continue;
            }
            $role = $node->style()->typeRole;
            if ($role === null || ! $brand->typography->hasRole($role)) {
                continue; // BrandTokenRule owns unresolvable roles.
            }

            $box = $node->declaredSize();
            $linesNeeded = $flow->linesNeeded($role, $node->content, $box->width);

            if ($linesNeeded > $node->maxLines) {
                $dropped = implode(' ', array_slice($flow->flow($role, $node->content, $box->width), $node->maxLines));
                $issues[] = new ValidationIssue(
                    IssueCode::TextOverflow,
                    (string) $node->id(),
                    sprintf(
                        "Text '%s' wraps to %d line(s) in a %.0fpx box at role '%s' but allows only %d, so rendering would drop: %s",
                        $node->id(), $linesNeeded, $box->width, $role, $node->maxLines, $dropped,
                    ),
                );
            }

            $heightNeeded = $brand->typography->role($role)->lineHeightPx() * min($linesNeeded, $node->maxLines);
            if ($heightNeeded > $box->height + 0.5) {
                $issues[] = new ValidationIssue(
                    IssueCode::TextOverflow,
                    (string) $node->id(),
                    sprintf(
                        "Text '%s' occupies %d line(s) needing about %.0fpx of height but declares only %.0fpx.",
                        $node->id(), min($linesNeeded, $node->maxLines), $heightNeeded, $box->height,
                    ),
                );
            }
        }

        return $issues;
    }
}
