<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

/**
 * Greedy word wrapping against the brand's declared advance ratios.
 *
 * This lives beside the brand rather than inside a renderer because validation and drawing
 * must agree by construction. When they were separate — the rule dividing width by allowed
 * lines, the renderer breaking on word boundaries — a caption could pass validation and then
 * render with its last word silently dropped.
 *
 * A word longer than the line is left to overflow rather than broken: hyphenation is a
 * typographic decision, and guessing at one silently would be worse than a visible problem
 * `linesNeeded()` already reports.
 */
final readonly class TextFlow
{
    public function __construct(private TypographySystem $typography) {}

    /** The lines this text actually occupies, with no truncation. */
    /** @return list<string> */
    public function flow(string $role, string $content, float $width): array
    {
        $words = preg_split('/\s+/u', trim($content)) ?: [];
        if ($words === [] || $words === ['']) {
            return [];
        }

        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($current !== '' && $this->typography->estimateWidthPx($role, $candidate) > $width) {
                $lines[] = $current;
                $current = $word;

                continue;
            }
            $current = $candidate;
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    public function linesNeeded(string $role, string $content, float $width): int
    {
        return max(1, count($this->flow($role, $content, $width)));
    }

    /** What a renderer draws: the flowed lines, cut to what the node allows. */
    /** @return list<string> */
    public function wrap(string $role, string $content, float $width, int $maxLines): array
    {
        return array_slice($this->flow($role, $content, $width), 0, $maxLines);
    }

    /** True when drawing would drop text the author wrote. */
    public function wouldTruncate(string $role, string $content, float $width, int $maxLines): bool
    {
        return $this->linesNeeded($role, $content, $width) > $maxLines;
    }
}
