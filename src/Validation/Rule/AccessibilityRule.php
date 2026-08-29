<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Composition\Node\ImageNode;
use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * The rules that keep a composition readable by someone who is not looking at it.
 *
 * Three separate things, all easy to forget and all cheap to check: images that say nothing
 * to a screen reader, essential text left out of the announced order, and meaning carried by
 * colour alone.
 */
final readonly class AccessibilityRule implements Rule
{
    public function name(): string
    {
        return 'accessibility';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $rules = $context->brand->accessibility;
        $scene = $context->scene;
        $issues = [];

        $announced = array_map(strval(...), $scene->readingOrder);

        foreach ($scene->nodes() as $node) {
            $path = (string) $node->id();

            if ($rules->requireTextAlternatives && $node instanceof ImageNode && ($node->textAlternative() ?? '') === '') {
                $issues[] = new ValidationIssue(IssueCode::TextAlternativeMissing, $path, "Image '{$path}' carries no text alternative.");
            }

            if ($node instanceof TextNode && $node->essential && ! in_array($path, $announced, true)) {
                $issues[] = new ValidationIssue(IssueCode::ReadingOrderIncomplete, $path, "Text '{$path}' is marked essential but is absent from the reading order.");
            }

            $semantic = $node->style()->semantic;
            if ($rules->forbidColorOnlyEncoding && $semantic !== null) {
                $carriesWords = $node instanceof TextNode || ($node->textAlternative() ?? '') !== '';
                if (! $carriesWords) {
                    $issues[] = new ValidationIssue(
                        IssueCode::ColorOnlyEncoding,
                        $path,
                        "Node '{$path}' encodes the meaning '{$semantic}' but carries no text or text alternative, so the meaning exists only in its colour.",
                    );
                }
            }
        }

        if ($scene->description === null || trim($scene->description) === '') {
            $issues[] = new ValidationIssue(IssueCode::TextAlternativeMissing, 'scene.description', 'The scene carries no accessible description.');
        }

        return $issues;
    }
}
