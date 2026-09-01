<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Composition;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Composition\CrossSizing;
use Sifrious\Rabo\Composition\Node\ShapeKind;
use Sifrious\Rabo\Composition\Node\ShapeNode;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\StackDirection;
use Sifrious\Rabo\Composition\Node\StackNode;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Render\FrozenClock;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Renderer\Svg\SvgStaticRenderer;
use Sifrious\Rabo\Validation\CompositionValidator;

/**
 * A second composition on the same brand.
 *
 * Its job is to find out whether the primitives generalise or were quietly shaped around the first
 * fixture. It deliberately differs in every structural way available: nested three deep rather than
 * two, ellipses rather than only rectangles, no connectors at all, the mark's mono variant, a 4:5
 * portrait derivation rather than a square, and no motion.
 */
final class SecondCompositionTest extends TestCase
{
    private const BUNDLE = 'green-checks-that-verified-nothing';

    public function test_a_second_composition_validates_against_the_same_brand(): void
    {
        $bundle = $this->bundle();
        $report = (new CompositionValidator())->validate(
            $bundle->composition, $bundle->brand, $bundle->assets, null, null, $bundle->assetRecords,
        );

        self::assertTrue($report->passed(), 'Issues: '.implode(', ', $report->codes()));
        self::assertSame([], $report->issues, 'This composition avoids the glyph the brand cannot draw, so it warns about nothing.');
        self::assertSame('burg', $bundle->composition->brandId);
    }

    public function test_a_bundle_without_motion_is_a_complete_bundle(): void
    {
        $bundle = $this->bundle();

        self::assertFalse($bundle->hasMotion());
        self::assertFileDoesNotExist($this->path().'/motion.json');
        self::assertNotNull($bundle->composition->scene);
    }

    public function test_it_uses_primitives_the_first_composition_never_did(): void
    {
        $scene = $this->bundle()->composition->scene;

        $ellipses = array_filter($scene->nodes(), static fn ($n): bool => $n instanceof ShapeNode && $n->shape === ShapeKind::Ellipse);
        self::assertCount(6, $ellipses, 'Six status dots, drawn as ellipses.');
        self::assertSame([], $scene->connectors, 'A scene with no connectors must lay out and render.');

        $depth = static function (self $t, $node) use (&$depth): int {
            if (! $node instanceof StackNode) {
                return 1;
            }
            $deepest = 0;
            foreach ($node->children() as $child) {
                $deepest = max($deepest, $depth($t, $child));
            }

            return 1 + $deepest;
        };
        self::assertGreaterThanOrEqual(4, $depth($this, $scene->root), 'Stacks nest deeper here than in the first fixture.');
    }

    public function test_it_places_the_marks_mono_variant(): void
    {
        $bundle = $this->bundle();
        $mark = $bundle->brand->mark('burg');
        $image = $bundle->composition->scene->findNode(new NodeId('mark'));

        self::assertTrue($image->asset->equals($mark->variant('mono')));
        self::assertFalse($image->asset->equals($mark->asset), 'The first composition used the full-colour mark.');
    }

    public function test_the_portrait_variant_flips_the_columns(): void
    {
        $composition = $this->bundle()->composition;

        $source = $composition->scene->findNode(new NodeId('columns'));
        $portrait = $composition->variantScene('portrait')->findNode(new NodeId('columns'));

        self::assertSame(StackDirection::Horizontal, $source->direction);
        self::assertSame(StackDirection::Vertical, $portrait->direction);
        self::assertSame(1080, $composition->variant('portrait')->canvas->width);
        self::assertSame(1350, $composition->variant('portrait')->canvas->height);
        self::assertSame('4:5', $composition->variant('portrait')->canvas->aspectRatio());
    }

    /**
     * Q-008, in the artifact that found it.
     *
     * Flipping the columns to a vertical stack in a 1000-wide root used to leave them at their
     * landscape measure of 560, with 440px of dead space beside content that was correct and legal
     * and simply did not use the room it was given.
     *
     * The title's box is asserted too: filling a container must not reflow the text inside it, or
     * the fill has quietly re-opened the question D-005 exists to keep closed.
     */
    public function test_the_portrait_variant_fills_the_width_it_is_given(): void
    {
        $composition = $this->bundle()->composition;
        $brand = $this->bundle()->brand;

        $source = $composition->scene->layout($brand);
        $portrait = $composition->variantScene('portrait')->layout($brand);

        foreach (['col-reported', 'col-actual'] as $card) {
            self::assertSame(560.0, $source->box(new NodeId($card))->width, "{$card} keeps its measure side by side.");
            self::assertSame(
                $portrait->box(new NodeId('root'))->width,
                $portrait->box(new NodeId($card))->width,
                "{$card} should span the root once the columns stack vertically.",
            );
        }

        self::assertSame(512.0, $portrait->box(new NodeId('col-reported-title'))->width, 'A declared text size is never filled.');
    }

    public function test_the_variant_derivation_keeps_the_fill_it_was_given(): void
    {
        $composition = $this->bundle()->composition;

        // applyOverrides() rebuilds this stack through withDirection(), which reconstructs
        // positionally. Dropping crossSizing there would compile, reset it, and leave the portrait
        // looking exactly as it did before the feature existed.
        self::assertSame(CrossSizing::Fill, $composition->scene->findNode(new NodeId('columns'))->crossSizing);
        self::assertSame(CrossSizing::Fill, $composition->variantScene('portrait')->findNode(new NodeId('columns'))->crossSizing);
    }

    public function test_it_embeds_only_the_typefaces_it_sets(): void
    {
        $svg = (string) file_get_contents($this->path().'/expected/static.svg');

        self::assertStringContainsString("font-family: 'Space Grotesk'", $svg);
        self::assertStringContainsString("font-family: 'Hanken Grotesk'", $svg);
        self::assertStringNotContainsString(
            "font-family: 'JetBrains Mono'",
            $svg,
            'This composition sets nothing in mono, so it must not carry a mono typeface.',
        );
    }

    public function test_both_scenes_regenerate_byte_identically(): void
    {
        $bundle = $this->bundle();
        $renderer = new SvgStaticRenderer($bundle->assets, new FrozenClock());

        foreach (['source' => 'static.svg', 'portrait' => 'static-portrait.svg'] as $scene => $file) {
            $canvas = $bundle->composition->allScenes()[$scene]->canvas;
            $outcome = $renderer->render(new RenderRequest($bundle->composition, $bundle->brand, $scene, new RenderTarget(RenderFormat::Svg, $canvas)));

            self::assertTrue($outcome->isSuccess());
            self::assertSame(file_get_contents($this->path().'/expected/'.$file), $outcome->artifactOrFail()->bytes);
        }
    }

    public function test_a_dot_that_means_something_also_says_it(): void
    {
        // Colour is not a channel on its own. Every dot carries a semantic tone, so every dot has
        // to carry words too, or RABO_COLOR_ONLY_ENCODING fires.
        foreach ($this->bundle()->composition->scene->nodes() as $node) {
            if (! $node instanceof ShapeNode || $node->style()->semantic === null) {
                continue;
            }
            self::assertNotSame('', (string) $node->textAlternative(), "Dot '{$node->id()}' encodes meaning in colour alone.");
        }
    }

    private function bundle(): CompositionBundle
    {
        return CompositionBundle::load($this->path());
    }

    private function path(): string
    {
        return dirname(__DIR__, 2).'/fixtures/'.self::BUNDLE;
    }
}
