<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Svg;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Node\Node;
use Sifrious\Rabo\Motion\Cue;
use Sifrious\Rabo\Motion\CueEffect;
use Sifrious\Rabo\Motion\MotionComposition;
use Sifrious\Rabo\Motion\ReducedMotionStrategy;
use Sifrious\Rabo\Motion\Timeline;
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
use Sifrious\Rabo\Validation\Rule\MotionRule;
use Sifrious\Rabo\Validation\ValidationReport;
use Sifrious\Rabo\Validation\ValidationContext;

/**
 * A deterministic motion renderer producing a self-contained animated SVG.
 *
 * CSS keyframes rather than a video codec, for the first proof: the output is text, so it can
 * be diffed, byte-compared, and read; it needs no binary to produce; and it keeps every word
 * selectable and announceable. A `prefers-reduced-motion` block is emitted alongside, so a
 * viewer whose system asks for less motion gets the final state from the same file — and a
 * separately addressable reduced-motion artifact is produced too, because a system preference
 * is not the only reason someone needs one.
 */
final readonly class SvgMotionRenderer implements Renderer
{
    public const IDENTITY = 'rabo-svg-motion';

    public const VERSION = '1.0.0';

    public function __construct(
        private MotionComposition $motion,
        private ?AssetStore $assets = null,
        private Clock $clock = new SystemClock(),
        private CompositionValidator $validator = new CompositionValidator(),
    ) {}

    public function capabilities(): RenderCapability
    {
        return new RenderCapability(
            self::IDENTITY,
            self::VERSION,
            [RenderFormat::SvgAnimated],
            16384,
            16384,
            true,
            600000,
        );
    }

    public function render(RenderRequest $request): RenderOutcome
    {
        $scene = $request->scene();
        $context = new ValidationContext(
            $request->composition,
            $request->brand,
            $request->scene,
            $scene,
            $this->assets,
            $this->motion,
            $this->capabilities(),
            $request->target,
        );

        $report = $this->validator->validateScene($context)
            ->merge(new ValidationReport((new MotionRule())->check($context)));
        if (! $report->passed()) {
            return RenderOutcome::refused($report);
        }

        $reduced = $request->target->reducedMotion;
        $timeline = $reduced ? $this->motion->reducedMotionTimeline($request->brand) : $this->motion->timeline;

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
        $this->paintStyles($svg, $request->brand, $timeline, $reduced);
        $painter->paintArrowheadDefs($svg, $scene);
        $painter->paintBackground($svg, $scene);

        $cueByNode = $this->cueByNode($timeline);
        $classFor = static function (Node $node) use ($cueByNode): array {
            $cue = $cueByNode[(string) $node->id()] ?? null;

            return $cue === null ? [] : ['class' => 'cue-'.$cue->id];
        };
        $painter->paintNodes($svg, $layout, $classFor);
        $painter->paintConnectors($svg, $scene, $layout, $classFor);
        $svg->close('svg', 0);

        $artifact = new RenderArtifact(
            $svg->toString(),
            RenderFormat::SvgAnimated->mediaType(),
            $scene->canvas,
            $timeline->duration->milliseconds,
            $this->motion->id.($reduced ? '.reduced-motion' : '').'.svg',
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
            ['motion_composition' => $this->motion->key(), 'reduced_motion' => $reduced ? 'true' : 'false'],
        ));
    }

    /** @return array<string,Cue> */
    private function cueByNode(Timeline $timeline): array
    {
        $map = [];
        foreach ($timeline->cues() as $cue) {
            $map[$cue->node->value] = $cue;
        }

        return $map;
    }

    private function paintStyles(SvgDocument $svg, BrandLibrary $brand, Timeline $timeline, bool $reduced): void
    {
        $total = $timeline->duration->milliseconds;
        $rules = [];
        $keyframes = [];

        foreach ($timeline->cues() as $cue) {
            $easing = $brand->motion->hasEasing($cue->easing->value)
                ? $brand->motion->easing($cue->easing->value)
                : 'linear';
            $rules[] = sprintf(
                '.cue-%s { opacity: 0; animation: rabo-%s %dms %s %dms both; }',
                $cue->id, $cue->effect->value, $cue->duration->milliseconds, $easing, $cue->start->milliseconds,
            );
        }

        $keyframes[] = '@keyframes rabo-reveal { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }';
        $keyframes[] = '@keyframes rabo-emphasise { from { opacity: 1; } 50% { opacity: 0.55; } to { opacity: 1; } }';
        $keyframes[] = '@keyframes rabo-dismiss { from { opacity: 1; } to { opacity: 0; } }';

        $svg->open('style');
        $svg->raw('/* Rendered by '.self::IDENTITY.' '.self::VERSION.'. Total '.$total.'ms. */', 2);
        $svg->raw('g { transform-box: fill-box; transform-origin: 50% 50%; }', 2);
        foreach ($keyframes as $frame) {
            $svg->raw($frame, 2);
        }
        foreach ($rules as $rule) {
            $svg->raw($rule, 2);
        }
        if (! $reduced) {
            $svg->raw('@media (prefers-reduced-motion: reduce) {', 2);
            $svg->raw('  g[class^="cue-"] { animation: none !important; opacity: 1 !important; transform: none !important; }', 2);
            $svg->raw('}', 2);
        }
        $svg->close('style');
    }

    private function describe(RenderRequest $request): string
    {
        $parts = [];
        if ($this->motion->description !== null) {
            $parts[] = $this->motion->description;
        }
        $scene = $request->scene();
        if ($scene->description !== null) {
            $parts[] = $scene->description;
        }
        foreach ($this->motion->captions() as $caption) {
            $parts[] = $caption;
        }

        return implode(' ', $parts);
    }
}
