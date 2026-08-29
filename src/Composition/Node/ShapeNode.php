<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

use InvalidArgumentException;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\NodeStyle;
use Sifrious\Rabo\Composition\Size;

/** A filled or stroked rectangle or ellipse: cards, badges, panels, dots. */
final readonly class ShapeNode implements Node
{
    public function __construct(
        private NodeId $id,
        public ShapeKind $shape,
        private Size $size,
        private NodeStyle $style,
        private ?string $textAlternative = null,
    ) {}

    public function id(): NodeId
    {
        return $this->id;
    }

    public function type(): string
    {
        return 'shape';
    }

    public function style(): NodeStyle
    {
        return $this->style;
    }

    public function declaredSize(): Size
    {
        return $this->size;
    }

    public function textAlternative(): ?string
    {
        return $this->textAlternative;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'id' => $this->id->value,
            'shape' => $this->shape->value,
            'size' => $this->size->toArray(),
            'style' => $this->style->toArray(),
            'text_alternative' => $this->textAlternative,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $shape = $serialized['shape'] ?? null;
        $size = $serialized['size'] ?? null;
        $style = $serialized['style'] ?? [];
        $alternative = $serialized['text_alternative'] ?? null;
        if (! is_string($id) || ! is_string($shape) || ! is_array($size) || ! is_array($style)) {
            throw new InvalidArgumentException('Serialized shape nodes require id, shape, size, and style.');
        }
        if ($alternative !== null && ! is_string($alternative)) {
            throw new InvalidArgumentException('Serialized text alternatives must be a string or null.');
        }

        return new self(
            new NodeId($id),
            ShapeKind::tryFrom($shape) ?? throw new InvalidArgumentException("Shape node '{$id}' declares an unsupported shape."),
            Size::fromArray($size),
            NodeStyle::fromArray($style),
            $alternative,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
