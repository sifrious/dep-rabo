<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Brand\TextFlow;
use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Composition\Scene;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Renderer\Svg\ScenePainter;
use Sifrious\Rabo\Renderer\Svg\SvgDocument;

/**
 * The rule and the painter must measure the same box.
 *
 * D-005 unified the wrapping *algorithm* — both sides call `TextFlow` — after a card passed
 * validation and then rendered with its last word silently dropped. It did not unify the *measure*.
 * `TextOverflowRule` read `declaredSize()` while `ScenePainter` wrapped against the box layout
 * resolved, and those two agreed only because layout could not yet produce a size a node had not
 * declared. Nothing asserted it.
 *
 * That coincidence is exactly what a cross-axis fill removes, so it is pinned here first, before
 * anything can rely on it. Two answers to one question is the bug D-005 is about, whichever half
 * of the question moves.
 */
final class TextMeasureAgreementTest extends TestCase
{
    public function test_the_rule_measures_the_box_the_painter_draws_into(): void
    {
        $compared = 0;

        foreach (self::bundles() as $label => $bundle) {
            foreach ($bundle->composition->allScenes() as $sceneName => $scene) {
                $flow = new TextFlow($bundle->brand->typography);
                $layout = $scene->layout($bundle->brand);

                foreach ($scene->nodes() as $node) {
                    if (! $node instanceof TextNode) {
                        continue;
                    }
                    $role = $node->style()->typeRole;
                    if ($role === null || ! $bundle->brand->typography->hasRole($role)) {
                        continue;
                    }

                    // The width the painter will wrap against, taken the way the painter takes it.
                    $width = $layout->box($node->id())->width;
                    $drawn = self::linesDrawnFor($bundle->brand, $scene, $node);
                    $expected = array_map(
                        SvgDocument::escape(...),
                        $flow->wrap($role, $node->content, $width, $node->maxLines),
                    );

                    self::assertSame(
                        $expected,
                        $drawn,
                        "{$label}/{$sceneName}/{$node->id()}: the painter drew something other than the wrap at the width the rule measures.",
                    );
                    $compared++;
                }
            }
        }

        self::assertGreaterThan(20, $compared, 'Too few text nodes were compared for this to mean anything.');
    }

    /**
     * The invariant that made the old code accidentally correct, now stated on purpose.
     *
     * A declared size is honoured exactly. If this ever fails, someone has taught layout to resize
     * a node that declared its own size, and the D-005 question — which measure does validation
     * use — has to be answered again deliberately rather than discovered in a rendered artifact.
     */
    public function test_a_declared_size_is_the_box_the_layout_resolves(): void
    {
        $checked = 0;

        foreach (self::bundles() as $label => $bundle) {
            foreach ($bundle->composition->allScenes() as $sceneName => $scene) {
                $layout = $scene->layout($bundle->brand);
                foreach ($scene->nodes() as $node) {
                    $declared = $node->declaredSize();
                    if ($declared === null || ! $layout->has($node->id())) {
                        continue;
                    }
                    $box = $layout->box($node->id());

                    self::assertSame($declared->width, $box->width, "{$label}/{$sceneName}/{$node->id()} width");
                    self::assertSame($declared->height, $box->height, "{$label}/{$sceneName}/{$node->id()} height");
                    $checked++;
                }
            }
        }

        self::assertGreaterThan(50, $checked);
    }

    /** The words the rule says would be dropped are exactly the words the painter does not draw. */
    public function test_the_rule_names_the_words_the_painter_actually_drops(): void
    {
        $bundle = CompositionBundle::load(dirname(__DIR__, 2).'/fixtures/failing/text-overflow');
        $scene = $bundle->composition->scene;
        $node = null;
        foreach ($scene->nodes() as $candidate) {
            if ($candidate instanceof TextNode) {
                $node = $candidate;
                break;
            }
        }
        self::assertInstanceOf(TextNode::class, $node);

        $role = (string) $node->style()->typeRole;
        $flow = new TextFlow($bundle->brand->typography);
        $width = $scene->layout($bundle->brand)->box($node->id())->width;

        $all = $flow->flow($role, $node->content, $width);
        $reported = array_slice($all, $node->maxLines);
        self::assertNotSame([], $reported, 'This fixture exists to overflow; if it no longer does, it stopped testing anything.');

        $drawn = self::linesDrawnFor($bundle->brand, $scene, $node);
        foreach ($reported as $line) {
            self::assertNotContains(SvgDocument::escape($line), $drawn, 'The rule reported a dropped line the painter still drew.');
        }
        self::assertCount($node->maxLines, $drawn);
    }

    /**
     * The text of every `<text>` element the painter emits for one node.
     *
     * Read back out of the painted document rather than recomputed, because a second computation
     * of the same number is what this test exists to stop anyone relying on. `SvgDocument` writes
     * one element per line, deterministically; `ext-dom` is not a declared dependency.
     *
     * @return list<string>
     */
    private static function linesDrawnFor(BrandLibrary $brand, Scene $scene, TextNode $node): array
    {
        $painter = new ScenePainter($brand);
        $document = new SvgDocument();
        $painter->paintNodes($document, $scene->layout($brand));

        $lines = [];
        $inside = false;
        foreach (explode("\n", $document->toString()) as $line) {
            $trimmed = trim($line);
            if (preg_match('/^<g id="([^"]+)"/', $trimmed, $match) === 1) {
                $inside = $match[1] === (string) $node->id();

                continue;
            }
            if ($inside && preg_match('/^<text\b.*?>(.*)<\/text>$/', $trimmed, $match) === 1) {
                $lines[] = $match[1];
            }
        }

        return $lines;
    }

    /** @return array<string,CompositionBundle> */
    private static function bundles(): array
    {
        $root = dirname(__DIR__, 2).'/fixtures/';

        return [
            'agent-completion' => CompositionBundle::load($root.'agent-completion-verified-completion'),
            'green-checks' => CompositionBundle::load($root.'green-checks-that-verified-nothing'),
        ];
    }
}
