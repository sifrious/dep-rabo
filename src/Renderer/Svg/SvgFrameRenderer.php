<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Svg;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Node\Node;
use Sifrious\Rabo\Composition\Scene;
use Sifrious\Rabo\Motion\CueSampler;
use Sifrious\Rabo\Motion\Duration;
use Sifrious\Rabo\Motion\Timeline;

/**
 * One still frame of a timeline, as SVG.
 *
 * This exists so an encoder never has to interpret animation. It asks for a picture at a time
 * and gets a plain, deterministic still — which means the frames feeding a video are exactly
 * as reproducible as the still renderer, and can be compared byte for byte in a test even
 * when the resulting MP4 cannot be.
 *
 * Font embedding defaults to off here. A frame is consumed by a rasterizer, which reads fonts from
 * files rather than from `@font-face`, so inlining ~130KB into each of several hundred frames would
 * cost a great deal and buy nothing.
 */
final readonly class SvgFrameRenderer
{
    public function __construct(
        private BrandLibrary $brand,
        private ?AssetStore $assets = null,
    ) {}

    public function frame(Scene $scene, Timeline $timeline, Duration $at, ?string $title = null, ?string $description = null, bool $embedFonts = false): string
    {
        $sampler = new CueSampler($this->brand);
        $painter = new ScenePainter($this->brand, $this->assets);
        $layout = $scene->layout($this->brand);
        $svg = new SvgDocument();

        $svg->raw('<?xml version="1.0" encoding="UTF-8"?>', 0);
        $svg->open('svg', [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'xmlns:xlink' => 'http://www.w3.org/1999/xlink',
            'width' => $scene->canvas->width,
            'height' => $scene->canvas->height,
            'viewBox' => '0 0 '.$scene->canvas->width.' '.$scene->canvas->height,
            'role' => 'img',
        ], 0);
        if ($title !== null) {
            $svg->element('title', $title);
        }
        if ($description !== null) {
            $svg->element('desc', $description);
        }
        if ($embedFonts) {
            $painter->paintFontFaces($svg, $scene);
        }
        $painter->paintArrowheadDefs($svg, $scene);
        $painter->paintBackground($svg, $scene);

        $attributes = static function (Node $node) use ($sampler, $timeline, $at): array {
            $state = $sampler->stateAt($timeline, (string) $node->id(), $at);
            if ($state->isDefault()) {
                return [];
            }

            return array_filter([
                'opacity' => SvgDocument::number($state->opacity),
                'transform' => abs($state->translateY) < 0.005 ? null : 'translate(0 '.SvgDocument::number($state->translateY).')',
            ], static fn (?string $value): bool => $value !== null);
        };

        $painter->paintNodes($svg, $layout, $attributes);
        $painter->paintConnectors($svg, $scene, $layout, $attributes);
        $svg->close('svg', 0);

        return $svg->toString();
    }

    /** Frame times for a timeline at a frame rate, inclusive of the final frame. */
    /** @return list<Duration> */
    public static function frameTimes(Timeline $timeline, int $framesPerSecond): array
    {
        $count = (int) max(1, round($timeline->duration->milliseconds * $framesPerSecond / 1000));
        $times = [];
        for ($index = 0; $index < $count; $index++) {
            $times[] = new Duration((int) round($index * 1000 / $framesPerSecond));
        }

        return $times;
    }
}
