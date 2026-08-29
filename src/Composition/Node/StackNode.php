<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

use InvalidArgumentException;
use Sifrious\Rabo\Composition\Alignment;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\NodeStyle;
use Sifrious\Rabo\Composition\Size;
use Sifrious\Rabo\Composition\StackDirection;

/**
 * Children packed along one axis.
 *
 * This is the primitive that makes an aspect-ratio variant cheap. A variant flips a stack
 * from horizontal to vertical and everything downstream — child positions, and the
 * connectors drawn between children — follows from the new geometry. No second composition
 * is authored and no coordinate is edited by hand.
 *
 * `gap` and the style's `padding` are brand spacing steps, not numbers, so spacing stays a
 * brand decision.
 */
final readonly class StackNode implements ContainerNode
{
    /** @var list<Node> */
    private array $children;

    /** @param list<Node> $children */
    public function __construct(
        private NodeId $id,
        public StackDirection $direction,
        public string $gap,
        array $children,
        private NodeStyle $style = new NodeStyle(),
        public Alignment $align = Alignment::Center,
        public Alignment $distribute = Alignment::Start,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $gap) !== 1) {
            throw new InvalidArgumentException("Stack '{$id}' must reference a brand spacing step for its gap.");
        }
        $seen = [];
        foreach ($children as $child) {
            if (! $child instanceof Node) {
                throw new InvalidArgumentException("Stack '{$id}' accepts Node children only.");
            }
            if (isset($seen[$child->id()->value])) {
                throw new InvalidArgumentException("Stack '{$id}' contains duplicate child identifier '{$child->id()}'.");
            }
            $seen[$child->id()->value] = true;
        }
        $this->children = array_values($children);
    }

    public function id(): NodeId
    {
        return $this->id;
    }

    public function type(): string
    {
        return 'stack';
    }

    public function style(): NodeStyle
    {
        return $this->style;
    }

    public function declaredSize(): ?Size
    {
        return null;
    }

    public function textAlternative(): ?string
    {
        return null;
    }

    /** @return list<Node> */
    public function children(): array
    {
        return $this->children;
    }

    /** A copy of this stack running along the other axis, keeping every identifier. */
    public function withDirection(StackDirection $direction): self
    {
        return new self($this->id, $direction, $this->gap, $this->children, $this->style, $this->align, $this->distribute);
    }

    /** A copy aligned differently across its axis, keeping every identifier. */
    public function withAlign(Alignment $align): self
    {
        return new self($this->id, $this->direction, $this->gap, $this->children, $this->style, $align, $this->distribute);
    }

    /** @param list<Node> $children */
    public function withChildren(array $children): self
    {
        return new self($this->id, $this->direction, $this->gap, $children, $this->style, $this->align, $this->distribute);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'id' => $this->id->value,
            'direction' => $this->direction->value,
            'gap' => $this->gap,
            'align' => $this->align->value,
            'distribute' => $this->distribute->value,
            'style' => $this->style->toArray(),
            'children' => array_map(static fn (Node $n): array => $n->toArray(), $this->children),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $direction = $serialized['direction'] ?? null;
        $gap = $serialized['gap'] ?? null;
        $align = $serialized['align'] ?? Alignment::Center->value;
        $distribute = $serialized['distribute'] ?? Alignment::Start->value;
        $style = $serialized['style'] ?? [];
        $children = $serialized['children'] ?? [];
        if (! is_string($id) || ! is_string($direction) || ! is_string($gap) || ! is_string($align) || ! is_string($distribute) || ! is_array($style) || ! is_array($children)) {
            throw new InvalidArgumentException('Serialized stacks require id, direction, gap, align, distribute, style, and children.');
        }

        return new self(
            new NodeId($id),
            StackDirection::tryFrom($direction) ?? throw new InvalidArgumentException("Stack '{$id}' declares an unsupported direction."),
            $gap,
            array_map(NodeFactory::fromArray(...), array_values($children)),
            NodeStyle::fromArray($style),
            Alignment::tryFrom($align) ?? throw new InvalidArgumentException("Stack '{$id}' declares an unsupported alignment."),
            Alignment::tryFrom($distribute) ?? throw new InvalidArgumentException("Stack '{$id}' declares an unsupported distribution."),
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
