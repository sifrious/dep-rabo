<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Svg;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Render\Clock;
use Sifrious\Rabo\Render\RenderArtifact;
use Sifrious\Rabo\Render\RenderCapability;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\Renderer;
use Sifrious\Rabo\Render\RenderOutcome;
use Sifrious\Rabo\Render\RenderProvenance;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\SystemClock;
use Sifrious\Rabo\Validation\CompositionValidator;
use Sifrious\Rabo\Validation\ValidationContext;

/**
 * A deterministic still renderer.
 *
 * SVG is chosen for the first proof because it is inspectable, diffable, byte-reproducible,
 * and keeps text as text — so the artifact itself remains partly accessible rather than
 * flattening the words into pixels. None of that makes SVG the domain model: this class is
 * one implementation of the renderer boundary and can be replaced without touching a scene.
 */
final readonly class SvgStaticRenderer implements Renderer
{
    public const IDENTITY = 'rabo-svg-static';

    public const VERSION = '1.0.0';

    public function __construct(
        private ?AssetStore $assets = null,
        private Clock $clock = new SystemClock(),
        private CompositionValidator $validator = new CompositionValidator(),
    ) {}

    public function capabilities(): RenderCapability
    {
        return new RenderCapability(self::IDENTITY, self::VERSION, [RenderFormat::Svg], 16384, 16384, true);
    }

    public function render(RenderRequest $request): RenderOutcome
    {
        $capability = $this->capabilities();
        $scene = $request->scene();

        $report = $this->validator->validateScene(new ValidationContext(
            $request->composition,
            $request->brand,
            $request->scene,
            $scene,
            $this->assets,
            null,
            $capability,
            $request->target,
        ));
        if (! $report->passed()) {
            return RenderOutcome::refused($report);
        }

        $painter = new ScenePainter($request->brand, $this->assets);
        $layout = $scene->layout($request->brand);
        $svg = new SvgDocument();

        $svg->raw('<?xml version="1.0" encoding="UTF-8"?>', 0);
        $svg->open('svg', [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'xmlns:xlink' => 'http://www.w3.org/1999/xlink',
            'width' => $scene->canvas->width,
            'height' => $scene->canvas->height,
            'viewBox' => '0 0 '.$scene->canvas->width.' '.$scene->canvas->height,
            'role' => 'img',
            'aria-labelledby' => 'rabo-title rabo-desc',
        ], 0);
        $svg->element('title', $request->composition->title ?? $request->composition->id, ['id' => 'rabo-title']);
        $svg->element('desc', $this->describe($request), ['id' => 'rabo-desc']);
        $painter->paintArrowheadDefs($svg, $scene);
        $painter->paintBackground($svg, $scene);
        $painter->paintNodes($svg, $layout);
        $painter->paintConnectors($svg, $scene, $layout);
        $svg->close('svg', 0);

        $artifact = new RenderArtifact(
            $svg->toString(),
            RenderFormat::Svg->mediaType(),
            $scene->canvas,
            null,
            $request->composition->id.'.'.$request->scene.'.svg',
        );

        return RenderOutcome::succeeded($artifact, new RenderProvenance(
            $request->composition->id,
            $request->composition->version,
            $request->composition->key(),
            $request->scene,
            $request->brand->id,
            (string) $request->brand->version,
            $request->brand->key(),
            $request->brand->referencedAssets(),
            self::IDENTITY,
            self::VERSION,
            $request->digest(),
            $artifact->digest,
            $request->composition->references,
            $this->clock->now(),
            true,
        ));
    }

    /**
     * The description a screen reader receives.
     *
     * Built from the authored description followed by the authored reading order, so what is
     * announced is what a person decided should be announced, in the order they chose.
     */
    private function describe(RenderRequest $request): string
    {
        $scene = $request->scene();
        $parts = $scene->description === null ? [] : [$scene->description];
        foreach ($scene->readingOrder as $nodeId) {
            $node = $scene->findNode($nodeId);
            $text = $node instanceof TextNode ? $node->content : $node?->textAlternative();
            if ($text !== null && trim($text) !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }
}
