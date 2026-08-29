<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

/** A node that places other nodes. */
interface ContainerNode extends Node
{
    /** @return list<Node> */
    public function children(): array;
}
