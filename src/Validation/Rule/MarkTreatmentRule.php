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
 * A mark must be given its declared minimum size and clearspace, and must draw in brand ink.
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

            foreach ($this->inkIssues($context, $node, $mark->id) as $issue) {
                $issues[] = $issue;
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
                // Containers are skipped because their extent is their children's, not their own —
                // and since D-017 a filled stack's box can span a whole track, which would other-
                // wise crowd every mark on the canvas with geometry nobody drew.
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

    /**
     * A mark drawn through an image cannot inherit the ink around it.
     *
     * The mono mark is authored `fill="currentColor"` so it takes the colour of whatever it sits
     * in. Placed as an `ImageNode` it becomes a separate document inside a data URI, where
     * `currentColor` resolves to that document's own default — black — rather than the brand's
     * `text-strong`, a warm near-black. So the mark renders subtly off-brand, everywhere, and
     * nothing said so.
     *
     * Inlining foreign SVG into the host document would fix it properly and is not obviously worth
     * the cost yet — Q-009. Until then the report says it rather than leaving it to be found in a
     * rendered artifact, which is this package's position on every other known-wrong output.
     *
     * @return list<ValidationIssue>
     */
    private function inkIssues(ValidationContext $context, ImageNode $node, string $markId): array
    {
        $store = $context->assets;
        if ($store === null || ! $store->has($node->asset)) {
            return []; // AssetRule owns a missing file.
        }

        $bytes = $store->bytes($node->asset);
        if (! str_contains($bytes, '<svg') || ! str_contains($bytes, 'currentColor')) {
            return [];
        }

        return [new ValidationIssue(
            IssueCode::MarkInkNotInherited,
            (string) $node->id(),
            sprintf(
                "Mark '%s' is drawn with currentColor inside its own document, so it takes that document's default ink rather than the brand colour around it.",
                $markId,
            ),
        )];
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
