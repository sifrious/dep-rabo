<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

use InvalidArgumentException;
use Sifrious\Rabo\Composition\Alignment;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\NodeStyle;
use Sifrious\Rabo\Composition\Size;

/**
 * Editable text.
 *
 * The content is a string, not a rendered path, which is the whole reason the composition
 * model exists: the headline can be changed without touching an SVG or a video frame. The
 * box it must fit in is declared, so overflow is a validation result rather than a
 * surprise in the artifact.
 */
final readonly class TextNode implements Node
{
    public function __construct(
        private NodeId $id,
        public string $content,
        private Size $size,
        private NodeStyle $style,
        public Alignment $align = Alignment::Start,
        public int $maxLines = 1,
        public bool $essential = true,
        private ?string $textAlternative = null,
    ) {
        if ($maxLines < 1 || $maxLines > 12) {
            throw new InvalidArgumentException("Text node '{$id}' must allow between 1 and 12 lines.");
        }
        if (trim($content) === '') {
            throw new InvalidArgumentException("Text node '{$id}' must carry content.");
        }
    }

    public function id(): NodeId
    {
        return $this->id;
    }

    public function type(): string
    {
        return 'text';
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
        return $this->textAlternative ?? $this->content;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'id' => $this->id->value,
            'content' => $this->content,
            'size' => $this->size->toArray(),
            'style' => $this->style->toArray(),
            'align' => $this->align->value,
            'max_lines' => $this->maxLines,
            'essential' => $this->essential,
            'text_alternative' => $this->textAlternative,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $content = $serialized['content'] ?? null;
        $size = $serialized['size'] ?? null;
        $style = $serialized['style'] ?? [];
        $align = $serialized['align'] ?? Alignment::Start->value;
        $maxLines = $serialized['max_lines'] ?? 1;
        $essential = $serialized['essential'] ?? true;
        $alternative = $serialized['text_alternative'] ?? null;
        if (! is_string($id) || ! is_string($content) || ! is_array($size) || ! is_array($style) || ! is_string($align) || ! is_int($maxLines) || ! is_bool($essential)) {
            throw new InvalidArgumentException('Serialized text nodes require id, content, size, style, align, max_lines, and essential.');
        }
        if ($alternative !== null && ! is_string($alternative)) {
            throw new InvalidArgumentException('Serialized text alternatives must be a string or null.');
        }

        return new self(
            new NodeId($id),
            $content,
            Size::fromArray($size),
            NodeStyle::fromArray($style),
            Alignment::tryFrom($align) ?? throw new InvalidArgumentException("Text node '{$id}' declares an unsupported alignment."),
            $maxLines,
            $essential,
            $alternative,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
