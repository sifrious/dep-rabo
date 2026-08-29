<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

use InvalidArgumentException;
use Sifrious\Rabo\Composition\UnsupportedNode;

/**
 * Reconstructs a node from its wire form.
 *
 * An unrecognised discriminator fails loudly. A renderer that silently skipped a node it
 * did not understand would produce a plausible artifact that is missing something, which is
 * the worst available outcome.
 */
final class NodeFactory
{
    /** @param mixed $serialized */
    public static function fromArray(mixed $serialized): Node
    {
        if (! is_array($serialized)) {
            throw new InvalidArgumentException('Serialized nodes must be objects.');
        }
        $type = $serialized['type'] ?? null;
        if (! is_string($type)) {
            throw new UnsupportedNode('Serialized nodes require a type discriminator.');
        }

        return match ($type) {
            'text' => TextNode::fromArray($serialized),
            'shape' => ShapeNode::fromArray($serialized),
            'image' => ImageNode::fromArray($serialized),
            'stack' => StackNode::fromArray($serialized),
            'connector' => ConnectorNode::fromArray($serialized),
            default => throw new UnsupportedNode("Rabo has no composition primitive of type '{$type}'."),
        };
    }
}
