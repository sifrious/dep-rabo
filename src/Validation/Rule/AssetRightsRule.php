<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Asset\Asset;
use Sifrious\Rabo\Composition\Node\ImageNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * Every placed asset must have declared rights.
 *
 * Rabo does not decide whether a licence permits a use. It refuses to let an artifact reach
 * review without anyone being able to say who owns the material in it.
 *
 * @param array<string,Asset> $known
 */
final readonly class AssetRightsRule implements Rule
{
    /** @param array<string,Asset> $known */
    public function __construct(private array $known = []) {}

    public function name(): string
    {
        return 'asset-rights';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        if ($this->known === []) {
            return [];
        }

        $issues = [];
        foreach ($context->scene->nodes() as $node) {
            if (! $node instanceof ImageNode) {
                continue;
            }
            $asset = $this->known[(string) $node->asset] ?? null;
            if ($asset === null) {
                $issues[] = new ValidationIssue(
                    IssueCode::AssetRightsMissing,
                    (string) $node->id(),
                    "Node '{$node->id()}' places {$node->asset}, for which no rights record was supplied.",
                );
            }
        }

        return $issues;
    }
}
