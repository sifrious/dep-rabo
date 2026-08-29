<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Brand\UnknownBrandToken;
use Sifrious\Rabo\Composition\Box;
use Sifrious\Rabo\Composition\Node\ImageNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * A mark must be given its declared minimum size and clearspace.
 *
 * Clearspace is measured against the laid-out geometry rather than the author's intent, so
 * a variant that packs the header tighter is caught the same as a hand-placed logo would be.
 */
final readonly class MarkTreatmentRule implements Rule
{
    public function name(): string
    {
        return 'mark-treatment';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $issues = [];
        $layout = $context->layout();

        foreach ($context->scene->nodes() as $node) {
            if (! $node instanceof ImageNode || $node->markId === null) {
                continue;
            }
            try {
                $mark = $context->brand->mark($node->markId);
            } catch (UnknownBrandToken) {
                $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, (string) $node->id(), "Node '{$node->id()}' places mark '{$node->markId}', which the brand does not declare.");

                continue;
            }

            $box = $layout->box($node->id());
            if ($box->width + 0.5 < $mark->minWidthPx) {
                $issues[] = new ValidationIssue(
                    IssueCode::LogoClearspaceViolated,
                    (string) $node->id(),
                    sprintf("Mark '%s' is drawn %.0fpx wide, below its declared %dpx minimum.", $mark->id, $box->width, $mark->minWidthPx),
                );
            }

            $required = $box->height * $mark->clearspaceRatio;
            foreach ($layout->placed as $placed) {
                $other = $placed->node;
                if ($other->id()->equals($node->id()) || $other->declaredSize() === null) {
                    continue;
                }
                $gap = $this->gapBetween($box, $placed->box);
                if ($gap !== null && $gap + 0.5 < $required) {
                    $issues[] = new ValidationIssue(
                        IssueCode::LogoClearspaceViolated,
                        (string) $node->id(),
                        sprintf("Mark '%s' requires %.0fpx of clearspace but sits %.0fpx from '%s'.", $mark->id, $required, $gap, $other->id()),
                    );
                    break;
                }
            }
        }

        return $issues;
    }

    /** Edge-to-edge distance, or null when the boxes do not share a band. */
    private function gapBetween(Box $mark, Box $other): ?float
    {
        $horizontallyAligned = $other->bottom() > $mark->y && $other->y < $mark->bottom();
        $verticallyAligned = $other->right() > $mark->x && $other->x < $mark->right();

        if ($horizontallyAligned && ! $verticallyAligned) {
            return $other->x >= $mark->right() ? $other->x - $mark->right() : $mark->x - $other->right();
        }
        if ($verticallyAligned && ! $horizontallyAligned) {
            return $other->y >= $mark->bottom() ? $other->y - $mark->bottom() : $mark->y - $other->bottom();
        }

        return null;
    }
}
