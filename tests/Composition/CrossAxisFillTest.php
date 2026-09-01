<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Composition;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Composition\Alignment;
use Sifrious\Rabo\Composition\CrossSizing;
use Sifrious\Rabo\Composition\Dimensions;
use Sifrious\Rabo\Composition\Layout;
use Sifrious\Rabo\Composition\Node\NodeFactory;
use Sifrious\Rabo\Composition\Node\ShapeKind;
use Sifrious\Rabo\Composition\Node\ShapeNode;
use Sifrious\Rabo\Composition\Node\StackNode;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\NodeStyle;
use Sifrious\Rabo\Composition\Scene;
use Sifrious\Rabo\Composition\Size;
use Sifrious\Rabo\Composition\StackDirection;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Tests\Fixture;

/**
 * `CrossSizing::Fill` — a stack giving its containers the whole track it has.
 *
 * The scene under test is the shape the problem actually takes: a narrow container beside a wide
 * leaf. The stack measures as wide as the leaf, and under `Hug` the container sits at its own
 * smaller measure with the difference left as dead space — which is Q-008, where two 560-wide
 * columns sat in a 1000-wide portrait root.
 *
 * The property that makes this safe is that it never touches a node which declared its own size.
 * Those are exactly the leaves, so no text box can change width, and the measure
 * `TextOverflowRule` reads stays the measure `ScenePainter` draws into.
 */
final class CrossAxisFillTest extends TestCase
{
    public function test_a_container_child_takes_the_whole_cross_track(): void
    {
        self::assertSame(200.0, self::layoutOf(CrossSizing::Hug)->box(new NodeId('inner'))->width);
        self::assertSame(500.0, self::layoutOf(CrossSizing::Fill)->box(new NodeId('inner'))->width);
    }

    public function test_a_child_that_declares_its_own_size_keeps_it(): void
    {
        foreach ([CrossSizing::Hug, CrossSizing::Fill] as $sizing) {
            $layout = self::layoutOf($sizing);

            self::assertSame(500.0, $layout->box(new NodeId('wide'))->width, 'A declared size is authored, not computed.');
            self::assertSame(200.0, $layout->box(new NodeId('leaf'))->width, 'Nor is one nested inside a filled container.');
        }
    }

    public function test_filling_does_not_change_what_the_parent_measures(): void
    {
        self::assertEquals(
            self::layoutOf(CrossSizing::Hug)->box(new NodeId('root'))->toArray(),
            self::layoutOf(CrossSizing::Fill)->box(new NodeId('root'))->toArray(),
            'Fill consumes space already allocated. If it grew the parent it would cascade upward.',
        );
    }

    public function test_fill_never_narrows_a_child(): void
    {
        self::assertGreaterThanOrEqual(
            self::layoutOf(CrossSizing::Hug)->box(new NodeId('inner'))->width,
            self::layoutOf(CrossSizing::Fill)->box(new NodeId('inner'))->width,
        );
    }

    public function test_the_enlarged_track_reaches_a_grandchild_that_also_fills(): void
    {
        $scene = new Scene(
            'nested',
            new Dimensions(600, 300),
            new StackNode(
                new NodeId('root'), StackDirection::Vertical, 'md',
                [
                    new StackNode(
                        new NodeId('inner'), StackDirection::Vertical, 'md',
                        [new StackNode(new NodeId('deep'), StackDirection::Vertical, 'md', [self::shape('leaf', 200.0)])],
                        new NodeStyle(), Alignment::Center, Alignment::Start, CrossSizing::Fill,
                    ),
                    self::shape('wide', 500.0),
                ],
                new NodeStyle(), Alignment::Start, Alignment::Start, CrossSizing::Fill,
            ),
        );
        $layout = $scene->layout(self::brand());

        self::assertSame(500.0, $layout->box(new NodeId('inner'))->width);
        self::assertSame(500.0, $layout->box(new NodeId('deep'))->width, 'A filled child hands its own track on.');
        self::assertSame(200.0, $layout->box(new NodeId('leaf'))->width, 'And the leaf at the bottom still declares its size.');
    }

    public function test_hug_is_what_every_existing_stack_does(): void
    {
        $explicit = self::layoutOf(CrossSizing::Hug);
        $default = self::layoutOf(null);

        foreach (['root', 'inner', 'leaf', 'wide'] as $id) {
            self::assertEquals($explicit->box(new NodeId($id))->toArray(), $default->box(new NodeId($id))->toArray(), $id);
        }
    }

    /**
     * The trap: every wither rebuilds through the constructor positionally.
     *
     * Forgetting to forward `crossSizing` still compiles and silently resets it — and
     * `Scene::applyOverrides()` calls `withDirection()` on exactly the stacks a variant re-orients,
     * so the fill would be dropped precisely where a variant needs it. The regenerated artifact
     * would look as though the feature did not work.
     */
    public function test_cross_sizing_survives_every_wither(): void
    {
        $stack = new StackNode(
            new NodeId('s'), StackDirection::Vertical, 'md', [self::shape('leaf', 200.0)],
            new NodeStyle(), Alignment::Center, Alignment::Start, CrossSizing::Fill,
        );

        self::assertSame(CrossSizing::Fill, $stack->withDirection(StackDirection::Horizontal)->crossSizing);
        self::assertSame(CrossSizing::Fill, $stack->withAlign(Alignment::End)->crossSizing);
        self::assertSame(CrossSizing::Fill, $stack->withChildren([self::shape('other', 100.0)])->crossSizing);
    }

    public function test_the_default_is_absent_from_the_serialized_form(): void
    {
        self::assertArrayNotHasKey(
            'cross_sizing',
            self::stack(CrossSizing::Hug)->toArray(),
            'A composition that does not use it must keep the key it has always had.',
        );
        self::assertSame('fill', self::stack(CrossSizing::Fill)->toArray()['cross_sizing']);
    }

    public function test_it_round_trips_through_the_serialized_form(): void
    {
        foreach ([CrossSizing::Hug, CrossSizing::Fill] as $sizing) {
            $stack = self::stack($sizing);
            $restored = NodeFactory::fromArray($stack->toArray());

            self::assertInstanceOf(StackNode::class, $restored);
            self::assertSame($sizing, $restored->crossSizing);
            self::assertSame($stack->toArray(), $restored->toArray());
        }
    }

    public function test_an_unsupported_cross_sizing_is_refused(): void
    {
        $serialized = self::stack(CrossSizing::Hug)->toArray();
        $serialized['cross_sizing'] = 'stretch-ish';

        $this->expectExceptionMessage('unsupported cross sizing');
        StackNode::fromArray($serialized);
    }

    private static function stack(CrossSizing $sizing): StackNode
    {
        return new StackNode(
            new NodeId('s'), StackDirection::Vertical, 'md', [self::shape('leaf', 200.0)],
            new NodeStyle(), Alignment::Center, Alignment::Start, $sizing,
        );
    }

    /** A narrow container beside a wide leaf: the stack's track is wider than the container's measure. */
    private static function layoutOf(?CrossSizing $sizing): Layout
    {
        $inner = new StackNode(new NodeId('inner'), StackDirection::Vertical, 'md', [self::shape('leaf', 200.0)]);
        $children = [$inner, self::shape('wide', 500.0)];

        $root = $sizing === null
            ? new StackNode(new NodeId('root'), StackDirection::Vertical, 'md', $children, new NodeStyle(), Alignment::Start)
            : new StackNode(new NodeId('root'), StackDirection::Vertical, 'md', $children, new NodeStyle(), Alignment::Start, Alignment::Start, $sizing);

        return (new Scene('fill', new Dimensions(600, 300), $root))->layout(self::brand());
    }

    private static function shape(string $id, float $width): ShapeNode
    {
        return new ShapeNode(new NodeId($id), ShapeKind::Rectangle, new Size($width, 40.0), new NodeStyle());
    }

    private static function brand(): \Sifrious\Rabo\Brand\BrandLibrary
    {
        return CompositionBundle::load(Fixture::path())->brand;
    }
}
