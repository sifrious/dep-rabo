<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

use InvalidArgumentException;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\NodeStyle;
use Sifrious\Rabo\Composition\Size;

/**
 * A line or arrow between two other nodes.
 *
 * Connectors name their endpoints by node identifier and are resolved after layout, so they
 * do not sit inside a stack and disturb its packing, and so they stay correct when a
 * variant flips that stack's axis: an arrow that pointed right in the landscape composition
 * points down in the square one without being re-authored.
 */
final readonly class ConnectorNode implements Node
{
    public function __construct(
        private NodeId $id,
        public NodeId $from,
        public NodeId $to,
        private NodeStyle $style,
        public bool $arrowhead = true,
        private ?string $textAlternative = null,
    ) {
        if ($from->equals($to)) {
            throw new InvalidArgumentException("Connector '{$id}' must join two different nodes.");
        }
    }

    public function id(): NodeId
    {
        return $this->id;
    }

    public function type(): string
    {
        return 'connector';
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
        return $this->textAlternative;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'id' => $this->id->value,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'style' => $this->style->toArray(),
            'arrowhead' => $this->arrowhead,
            'text_alternative' => $this->textAlternative,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $from = $serialized['from'] ?? null;
        $to = $serialized['to'] ?? null;
        $style = $serialized['style'] ?? [];
        $arrowhead = $serialized['arrowhead'] ?? true;
        $alternative = $serialized['text_alternative'] ?? null;
        if (! is_string($id) || ! is_string($from) || ! is_string($to) || ! is_array($style) || ! is_bool($arrowhead)) {
            throw new InvalidArgumentException('Serialized connectors require id, from, to, style, and arrowhead.');
        }
        if ($alternative !== null && ! is_string($alternative)) {
            throw new InvalidArgumentException('Serialized text alternatives must be a string or null.');
        }

        return new self(new NodeId($id), new NodeId($from), new NodeId($to), NodeStyle::fromArray($style), $arrowhead, $alternative);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
