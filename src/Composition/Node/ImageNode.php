<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

use InvalidArgumentException;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\NodeStyle;
use Sifrious\Rabo\Composition\Size;

/**
 * An asset placed in a composition.
 *
 * The asset is named by content digest, never by path, so a composition that renders today
 * renders identically wherever those bytes are found tomorrow.
 */
final readonly class ImageNode implements Node
{
    public function __construct(
        private NodeId $id,
        public ContentDigest $asset,
        private Size $size,
        private NodeStyle $style,
        private ?string $textAlternative = null,
        public ?string $markId = null,
    ) {}

    public function id(): NodeId
    {
        return $this->id;
    }

    public function type(): string
    {
        return 'image';
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
            'asset' => (string) $this->asset,
            'size' => $this->size->toArray(),
            'style' => $this->style->toArray(),
            'text_alternative' => $this->textAlternative,
            'mark_id' => $this->markId,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $asset = $serialized['asset'] ?? null;
        $size = $serialized['size'] ?? null;
        $style = $serialized['style'] ?? [];
        $alternative = $serialized['text_alternative'] ?? null;
        $markId = $serialized['mark_id'] ?? null;
        if (! is_string($id) || ! is_string($asset) || ! is_array($size) || ! is_array($style)) {
            throw new InvalidArgumentException('Serialized image nodes require id, asset, size, and style.');
        }
        if ($alternative !== null && ! is_string($alternative)) {
            throw new InvalidArgumentException('Serialized text alternatives must be a string or null.');
        }
        if ($markId !== null && ! is_string($markId)) {
            throw new InvalidArgumentException('Serialized image mark identifiers must be a string or null.');
        }

        return new self(new NodeId($id), ContentDigest::parse($asset), Size::fromArray($size), NodeStyle::fromArray($style), $alternative, $markId);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
