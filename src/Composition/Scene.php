<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Node\ConnectorNode;
use Sifrious\Rabo\Composition\Node\ContainerNode;
use Sifrious\Rabo\Composition\Node\Node;
use Sifrious\Rabo\Composition\Node\NodeFactory;
use Sifrious\Rabo\Composition\Node\StackNode;

/**
 * One editable composition.
 *
 * A scene is a tree of primitives plus the connectors between them, a declared reading
 * order, and a description. It is renderer-neutral: nothing here knows about SVG, Canvas, or
 * a video encoder.
 *
 * Reading order is authored rather than derived from position, because the order a sighted
 * reader takes from a diagram's layout and the order a screen reader should announce are
 * not always the same, and only a person knows which is intended.
 */
final readonly class Scene implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.scene';

    public const CONTRACT_VERSION = 1;

    /** @var list<ConnectorNode> */
    public array $connectors;

    /** @var list<NodeId> */
    public array $readingOrder;

    /**
     * @param  list<ConnectorNode>  $connectors
     * @param  list<NodeId>  $readingOrder
     */
    public function __construct(
        public string $id,
        public Dimensions $canvas,
        public Node $root,
        array $connectors = [],
        array $readingOrder = [],
        public string $padding = 'lg',
        public Alignment $alignHorizontal = Alignment::Center,
        public Alignment $alignVertical = Alignment::Center,
        public ?string $background = null,
        public ?string $description = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*(\.[a-z][a-z0-9]*(-[a-z0-9]+)*)?$/', $id) !== 1) {
            throw new InvalidArgumentException('Scene identifiers must be lowercase kebab-case, optionally suffixed with a dotted variant name.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $padding) !== 1) {
            throw new InvalidArgumentException("Scene '{$id}' padding must be a brand spacing step.");
        }
        if ($background !== null && preg_match('/^[a-z0-9][a-z0-9-]*$/', $background) !== 1) {
            throw new InvalidArgumentException("Scene '{$id}' background must be a brand colour role.");
        }

        $known = [];
        foreach (self::walk($root) as $node) {
            if (isset($known[$node->id()->value])) {
                throw new InvalidArgumentException("Scene '{$id}' reuses node identifier '{$node->id()}'.");
            }
            $known[$node->id()->value] = true;
        }
        foreach ($connectors as $connector) {
            if (! $connector instanceof ConnectorNode) {
                throw new InvalidArgumentException("Scene '{$id}' accepts ConnectorNode connectors only.");
            }
            if (isset($known[$connector->id()->value])) {
                throw new InvalidArgumentException("Scene '{$id}' reuses node identifier '{$connector->id()}'.");
            }
            $known[$connector->id()->value] = true;
            foreach ([$connector->from, $connector->to] as $endpoint) {
                if (! isset($known[$endpoint->value])) {
                    throw new InvalidArgumentException("Connector '{$connector->id()}' names unknown node '{$endpoint}'.");
                }
            }
        }
        foreach ($readingOrder as $nodeId) {
            if (! $nodeId instanceof NodeId) {
                throw new InvalidArgumentException("Scene '{$id}' reading order accepts NodeId values only.");
            }
            if (! isset($known[$nodeId->value])) {
                throw new InvalidArgumentException("Scene '{$id}' reading order names unknown node '{$nodeId}'.");
            }
        }

        $this->connectors = array_values($connectors);
        $this->readingOrder = array_values($readingOrder);
    }

    public function layout(BrandLibrary $brand): Layout
    {
        return Layout::of($this, $brand);
    }

    /** Every node in the tree, parents before children, in a stable order. */
    /** @return list<Node> */
    public function nodes(): array
    {
        return self::walk($this->root);
    }

    public function findNode(NodeId $id): ?Node
    {
        foreach ($this->nodes() as $node) {
            if ($node->id()->equals($id)) {
                return $node;
            }
        }
        foreach ($this->connectors as $connector) {
            if ($connector->id()->equals($id)) {
                return $connector;
            }
        }

        return null;
    }

    /**
     * A derived scene for a variant. The source scene is untouched.
     *
     * Only the canvas, padding, and named stack axes change; every identifier, string, and
     * style reference is carried across, so the two outputs cannot drift apart in content.
     */
    public function deriveVariant(VariantSpec $variant): self
    {
        return new self(
            $this->id.'.'.$variant->id,
            $variant->canvas,
            self::applyOverrides($this->root, $variant),
            $this->connectors,
            $this->readingOrder,
            $variant->padding ?? $this->padding,
            $this->alignHorizontal,
            $this->alignVertical,
            $this->background,
            $this->description,
        );
    }

    /** A stable identity for this exact scene content. */
    public function key(): string
    {
        return hash('sha256', $this->canonical());
    }

    public function canonical(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function equals(self $other): bool
    {
        return $this->canonical() === $other->canonical();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'id' => $this->id,
            'canvas' => $this->canvas->toArray(),
            'padding' => $this->padding,
            'align_horizontal' => $this->alignHorizontal->value,
            'align_vertical' => $this->alignVertical->value,
            'background' => $this->background,
            'description' => $this->description,
            'root' => $this->root->toArray(),
            'connectors' => array_map(static fn (ConnectorNode $c): array => $c->toArray(), $this->connectors),
            'reading_order' => array_map(static fn (NodeId $n): string => $n->value, $this->readingOrder),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo scene contract.');
        }
        $id = $serialized['id'] ?? null;
        $canvas = $serialized['canvas'] ?? null;
        $root = $serialized['root'] ?? null;
        $connectors = $serialized['connectors'] ?? [];
        $readingOrder = $serialized['reading_order'] ?? [];
        $padding = $serialized['padding'] ?? 'lg';
        $alignH = $serialized['align_horizontal'] ?? Alignment::Center->value;
        $alignV = $serialized['align_vertical'] ?? Alignment::Center->value;
        $background = $serialized['background'] ?? null;
        $description = $serialized['description'] ?? null;
        if (! is_string($id) || ! is_array($canvas) || ! is_array($root) || ! is_array($connectors) || ! is_array($readingOrder)) {
            throw new InvalidArgumentException('Serialized scenes require id, canvas, root, connectors, and reading_order.');
        }
        if (! is_string($padding) || ! is_string($alignH) || ! is_string($alignV)) {
            throw new InvalidArgumentException('Serialized scenes require string padding and alignment values.');
        }
        if ($background !== null && ! is_string($background)) {
            throw new InvalidArgumentException('Serialized scene backgrounds must be a string or null.');
        }
        if ($description !== null && ! is_string($description)) {
            throw new InvalidArgumentException('Serialized scene descriptions must be a string or null.');
        }

        return new self(
            $id,
            Dimensions::fromArray($canvas),
            NodeFactory::fromArray($root),
            array_map(static function (mixed $c): ConnectorNode {
                if (! is_array($c)) {
                    throw new InvalidArgumentException('Serialized connectors must be objects.');
                }

                return ConnectorNode::fromArray($c);
            }, array_values($connectors)),
            array_map(static function (mixed $n): NodeId {
                if (! is_string($n)) {
                    throw new InvalidArgumentException('Serialized reading orders must be node identifier strings.');
                }

                return new NodeId($n);
            }, array_values($readingOrder)),
            $padding,
            Alignment::tryFrom($alignH) ?? throw new InvalidArgumentException("Scene '{$id}' declares an unsupported horizontal alignment."),
            Alignment::tryFrom($alignV) ?? throw new InvalidArgumentException("Scene '{$id}' declares an unsupported vertical alignment."),
            $background,
            $description,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return list<Node> */
    private static function walk(Node $node): array
    {
        $nodes = [$node];
        if ($node instanceof ContainerNode) {
            foreach ($node->children() as $child) {
                $nodes = [...$nodes, ...self::walk($child)];
            }
        }

        return $nodes;
    }

    private static function applyOverrides(Node $node, VariantSpec $variant): Node
    {
        if (! $node instanceof StackNode) {
            return $node;
        }
        $rebuilt = $node->withChildren(array_map(
            static fn (Node $child): Node => self::applyOverrides($child, $variant),
            $node->children(),
        ));

        $direction = $variant->stackDirections[$node->id()->value] ?? null;
        if ($direction !== null) {
            $rebuilt = $rebuilt->withDirection($direction);
        }
        $alignment = $variant->stackAlignments[$node->id()->value] ?? null;
        if ($alignment !== null) {
            $rebuilt = $rebuilt->withAlign($alignment);
        }

        return $rebuilt;
    }
}
