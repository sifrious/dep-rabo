<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Composition\Node\Node;
use Sifrious\Rabo\Composition\Node\StackNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * Every style reference must name something the brand declares.
 *
 * This is what stops a composition drifting away from its brand by inventing a colour. A
 * token that does not resolve is an error, never a silent fallback to a default.
 */
final readonly class BrandTokenRule implements Rule
{
    public function name(): string
    {
        return 'brand-token';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $brand = $context->brand;
        $issues = [];

        $nodes = [...$context->scene->nodes(), ...$context->scene->connectors];
        foreach ($nodes as $node) {
            $path = (string) $node->id();
            $style = $node->style();

            foreach (['fill' => $style->fill, 'text' => $style->text, 'stroke' => $style->stroke] as $field => $role) {
                if ($role !== null && ! $brand->colors->hasRole($role)) {
                    $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, $path, "Node '{$path}' uses colour role '{$role}' for {$field}, which brand '{$brand->id}' does not declare.");
                }
            }
            if ($style->radius !== null && ! $brand->radii->has($style->radius)) {
                $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, $path, "Node '{$path}' uses radius step '{$style->radius}', which brand '{$brand->id}' does not declare.");
            }
            if ($style->strokeWidth !== null && ! $brand->strokes->has($style->strokeWidth)) {
                $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, $path, "Node '{$path}' uses stroke width '{$style->strokeWidth}', which brand '{$brand->id}' does not declare.");
            }
            if ($style->padding !== null && ! $brand->spacing->has($style->padding)) {
                $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, $path, "Node '{$path}' uses spacing step '{$style->padding}' for padding, which brand '{$brand->id}' does not declare.");
            }
            if ($style->typeRole !== null && ! $brand->typography->hasRole($style->typeRole)) {
                $issues[] = new ValidationIssue(IssueCode::BrandFontUnknown, $path, "Node '{$path}' uses type role '{$style->typeRole}', which brand '{$brand->id}' does not declare.");
            }
            if ($node instanceof StackNode && ! $brand->spacing->has($node->gap)) {
                $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, $path, "Stack '{$path}' uses spacing step '{$node->gap}' for its gap, which brand '{$brand->id}' does not declare.");
            }
        }

        if ($context->scene->background !== null && ! $brand->colors->hasRole($context->scene->background)) {
            $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, 'scene.background', "The scene background uses colour role '{$context->scene->background}', which brand '{$brand->id}' does not declare.");
        }
        if (! $brand->spacing->has($context->scene->padding)) {
            $issues[] = new ValidationIssue(IssueCode::BrandTokenUnknown, 'scene.padding', "The scene padding uses spacing step '{$context->scene->padding}', which brand '{$brand->id}' does not declare.");
        }

        return $issues;
    }
}
