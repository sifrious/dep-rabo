<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

/** What a caller wants back. Rabo names the shape of the output, never the tool. */
enum RenderFormat: string
{
    case Svg = 'svg';
    case SvgAnimated = 'svg-animated';
    case Png = 'png';
    case Mp4 = 'mp4';

    public function mediaType(): string
    {
        return match ($this) {
            self::Svg, self::SvgAnimated => 'image/svg+xml',
            self::Png => 'image/png',
            self::Mp4 => 'video/mp4',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Svg, self::SvgAnimated => 'svg',
            self::Png => 'png',
            self::Mp4 => 'mp4',
        };
    }

    public function isTemporal(): bool
    {
        return $this === self::SvgAnimated || $this === self::Mp4;
    }
}
