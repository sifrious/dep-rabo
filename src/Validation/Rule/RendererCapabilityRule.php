<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/** The chosen renderer must declare that it can produce what was asked for. */
final readonly class RendererCapabilityRule implements Rule
{
    public function name(): string
    {
        return 'renderer-capability';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $capability = $context->capability;
        $target = $context->target;
        if ($capability === null || $target === null) {
            return [];
        }

        $issues = [];
        if (! in_array($target->format, $capability->formats, true)) {
            $issues[] = new ValidationIssue(
                IssueCode::RendererCapabilityUnsupported,
                'target.format',
                sprintf(
                    "Renderer '%s' %s produces %s, not '%s'.",
                    $capability->renderer, $capability->version,
                    implode(', ', array_map(static fn ($f): string => $f->value, $capability->formats)),
                    $target->format->value,
                ),
            );
        }
        if ($target->dimensions->width > $capability->maxWidth || $target->dimensions->height > $capability->maxHeight) {
            $issues[] = new ValidationIssue(
                IssueCode::RendererCapabilityUnsupported,
                'target.dimensions',
                sprintf(
                    "Renderer '%s' renders at most %dx%d, below the requested %dx%d.",
                    $capability->renderer, $capability->maxWidth, $capability->maxHeight,
                    $target->dimensions->width, $target->dimensions->height,
                ),
            );
        }
        if (! $context->brand->declaresRendererCompatibility($capability->renderer)) {
            $issues[] = new ValidationIssue(
                IssueCode::RendererCapabilityUnsupported,
                'brand.compatible_renderers',
                sprintf("Brand '%s' does not list renderer '%s' as compatible.", $context->brand->id, $capability->renderer),
            );
        }

        return $issues;
    }
}
