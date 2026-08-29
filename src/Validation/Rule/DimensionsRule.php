<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/** The laid-out content must actually fit inside the canvas it was given. */
final readonly class DimensionsRule implements Rule
{
    public function name(): string
    {
        return 'dimensions';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $scene = $context->scene;
        $root = $context->layout()->box($scene->root->id());
        $issues = [];

        $overflows = [
            'left' => $root->x < -0.5,
            'top' => $root->y < -0.5,
            'right' => $root->right() > $scene->canvas->width + 0.5,
            'bottom' => $root->bottom() > $scene->canvas->height + 0.5,
        ];
        $breached = array_keys(array_filter($overflows));
        if ($breached !== []) {
            $issues[] = new ValidationIssue(
                IssueCode::DimensionsInvalid,
                (string) $scene->root->id(),
                sprintf(
                    'Laid-out content measures %.0fx%.0f at (%.0f, %.0f) and leaves the %dx%d canvas at the %s.',
                    $root->width, $root->height, $root->x, $root->y,
                    $scene->canvas->width, $scene->canvas->height, implode(' and ', $breached),
                ),
            );
        }

        if ($context->target !== null && ! $context->target->dimensions->equals($scene->canvas)) {
            $issues[] = new ValidationIssue(
                IssueCode::DimensionsInvalid,
                'target.dimensions',
                sprintf(
                    'The target asks for %dx%d but scene "%s" is authored at %dx%d.',
                    $context->target->dimensions->width, $context->target->dimensions->height,
                    $context->sceneName, $scene->canvas->width, $scene->canvas->height,
                ),
            );
        }

        return $issues;
    }
}
