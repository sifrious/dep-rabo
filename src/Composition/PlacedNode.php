<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use Sifrious\Rabo\Composition\Node\Node;

/** A node and the absolute rectangle layout gave it. */
final readonly class PlacedNode
{
    public function __construct(public Node $node, public Box $box) {}
}
