<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use Sifrious\Rabo\Composition\Node\ConnectorNode;
use Sifrious\Rabo\Composition\Node\Node;
use Sifrious\Rabo\Composition\Node\StackNode;
use Sifrious\Rabo\Brand\BrandLibrary;

/**
 * The resolved geometry of one scene against one brand.
 *
 * Layout is a pure function of scene plus brand. Running it twice produces identical boxes,
 * which is what lets a rendered artifact be compared byte for byte.
 */
final readonly class Layout
{
    /** @var list<PlacedNode> */
    public array $placed;

    /** @var array<string,Box> */
    private array $boxes;

    /** @param list<PlacedNode> $placed */
    private function __construct(public Dimensions $canvas, array $placed)
    {
        $boxes = [];
        foreach ($placed as $node) {
            $boxes[$node->node->id()->value] = $node->box;
        }
        $this->placed = array_values($placed);
        $this->boxes = $boxes;
    }

    public static function of(Scene $scene, BrandLibrary $brand): self
    {
        $padding = $brand->spacing->step($scene->padding);
        $available = new Size(
            max(0.0, $scene->canvas->width - 2 * $padding),
            max(0.0, $scene->canvas->height - 2 * $padding),
        );
        $rootSize = self::measure($scene->root, $brand);

        $origin = new Box(
            $padding + $scene->alignHorizontal->offsetWithin($available->width, $rootSize->width),
            $padding + $scene->alignVertical->offsetWithin($available->height, $rootSize->height),
            $rootSize->width,
            $rootSize->height,
        );

        $placed = [];
        self::place($scene->root, $origin, $brand, $placed);

        return new self($scene->canvas, $placed);
    }

    public function has(NodeId $id): bool
    {
        return isset($this->boxes[$id->value]);
    }

    public function box(NodeId $id): Box
    {
        return $this->boxes[$id->value]
            ?? throw new UnsupportedNode("Layout has no node '{$id}'.");
    }

    /**
     * Edge-to-edge geometry for a connector, along whichever axis dominates.
     *
     * This is why an arrow survives an aspect-ratio change untouched: the axis is read from
     * the resolved boxes, not from the author's intent at the time of writing.
     *
     * @return array{x1:float,y1:float,x2:float,y2:float}
     */
    public function connectorPath(ConnectorNode $connector): array
    {
        $from = $this->box($connector->from);
        $to = $this->box($connector->to);
        $dx = $to->centerX() - $from->centerX();
        $dy = $to->centerY() - $from->centerY();

        if (abs($dx) >= abs($dy)) {
            return $dx >= 0
                ? ['x1' => $from->right(), 'y1' => $from->centerY(), 'x2' => $to->x, 'y2' => $to->centerY()]
                : ['x1' => $from->x, 'y1' => $from->centerY(), 'x2' => $to->right(), 'y2' => $to->centerY()];
        }

        return $dy >= 0
            ? ['x1' => $from->centerX(), 'y1' => $from->bottom(), 'x2' => $to->centerX(), 'y2' => $to->y]
            : ['x1' => $from->centerX(), 'y1' => $from->y, 'x2' => $to->centerX(), 'y2' => $to->bottom()];
    }

    private static function measure(Node $node, BrandLibrary $brand): Size
    {
        $declared = $node->declaredSize();
        if ($declared !== null) {
            return $declared;
        }
        if (! $node instanceof StackNode) {
            return new Size(0.0, 0.0);
        }

        $gap = $brand->spacing->step($node->gap);
        $pad = $node->style()->padding === null ? 0.0 : $brand->spacing->step($node->style()->padding);
        $children = $node->children();

        $main = 0.0;
        $cross = 0.0;
        foreach ($children as $index => $child) {
            $size = self::measure($child, $brand);
            $childMain = $node->direction === StackDirection::Horizontal ? $size->width : $size->height;
            $childCross = $node->direction === StackDirection::Horizontal ? $size->height : $size->width;
            $main += $childMain + ($index > 0 ? $gap : 0.0);
            $cross = max($cross, $childCross);
        }

        return $node->direction === StackDirection::Horizontal
            ? new Size($main + 2 * $pad, $cross + 2 * $pad)
            : new Size($cross + 2 * $pad, $main + 2 * $pad);
    }

    /** @param list<PlacedNode> $placed */
    private static function place(Node $node, Box $box, BrandLibrary $brand, array &$placed): void
    {
        $placed[] = new PlacedNode($node, $box);
        if (! $node instanceof StackNode) {
            return;
        }

        $gap = $brand->spacing->step($node->gap);
        $pad = $node->style()->padding === null ? 0.0 : $brand->spacing->step($node->style()->padding);
        $horizontal = $node->direction === StackDirection::Horizontal;

        $innerMain = ($horizontal ? $box->width : $box->height) - 2 * $pad;
        $innerCross = ($horizontal ? $box->height : $box->width) - 2 * $pad;

        $contentMain = 0.0;
        $sizes = [];
        foreach ($node->children() as $index => $child) {
            $sizes[$index] = self::measure($child, $brand);
            $contentMain += ($horizontal ? $sizes[$index]->width : $sizes[$index]->height) + ($index > 0 ? $gap : 0.0);
        }

        $cursor = $pad + $node->distribute->offsetWithin($innerMain, $contentMain);
        foreach ($node->children() as $index => $child) {
            $size = $sizes[$index];
            $childMain = $horizontal ? $size->width : $size->height;
            $childCross = $horizontal ? $size->height : $size->width;
            $crossOffset = $pad + $node->align->offsetWithin($innerCross, $childCross);

            $childBox = $horizontal
                ? new Box($box->x + $cursor, $box->y + $crossOffset, $size->width, $size->height)
                : new Box($box->x + $crossOffset, $box->y + $cursor, $size->width, $size->height);

            self::place($child, $childBox, $brand, $placed);
            $cursor += $childMain + $gap;
        }
    }
}
