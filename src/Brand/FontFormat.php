<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

/**
 * A font file format.
 *
 * Two are needed because no single one satisfies both consumers: browsers take WOFF2 through an
 * `@font-face` data URI, and the rasterizers used here reject WOFF2 outright and want TrueType.
 */
enum FontFormat: string
{
    case Woff2 = 'woff2';
    case TrueType = 'truetype';

    public function mediaType(): string
    {
        return match ($this) {
            self::Woff2 => 'font/woff2',
            self::TrueType => 'font/ttf',
        };
    }

    /** The token a CSS `src` descriptor expects. */
    public function cssFormat(): string
    {
        return match ($this) {
            self::Woff2 => 'woff2',
            self::TrueType => 'truetype',
        };
    }

    /** Whether a renderer can inline this format into a document as a data URI. */
    public function isEmbeddable(): bool
    {
        return $this === self::Woff2;
    }
}
