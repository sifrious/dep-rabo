<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A node's appearance, expressed only as references into the Brand Library.
 *
 * No literal colour, size, or radius appears here. That is what lets one composition be
 * re-branded without editing it, and what lets validation prove a composition uses nothing
 * the brand does not declare.
 *
 * `semantic` names the meaning a node carries — "claim", "verified". If a node means
 * something, it must say so in words too; colour alone is not a channel.
 */
final readonly class NodeStyle implements JsonSerializable
{
    public function __construct(
        public ?string $fill = null,
        public ?string $text = null,
        public ?string $typeRole = null,
        public ?string $stroke = null,
        public ?string $strokeWidth = null,
        public ?string $radius = null,
        public ?string $padding = null,
        public ?string $semantic = null,
        public float $opacity = 1.0,
    ) {
        foreach (['fill' => $fill, 'text' => $text, 'type_role' => $typeRole, 'stroke' => $stroke, 'stroke_width' => $strokeWidth, 'radius' => $radius, 'padding' => $padding, 'semantic' => $semantic] as $field => $value) {
            if ($value !== null && preg_match('/^[a-z0-9][a-z0-9-]*$/', $value) !== 1) {
                throw new InvalidArgumentException("Style field '{$field}' must be a lowercase brand token reference.");
            }
        }
        if ($opacity < 0.0 || $opacity > 1.0) {
            throw new InvalidArgumentException('Style opacity must be between 0 and 1.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'fill' => $this->fill,
            'text' => $this->text,
            'type_role' => $this->typeRole,
            'stroke' => $this->stroke,
            'stroke_width' => $this->strokeWidth,
            'radius' => $this->radius,
            'padding' => $this->padding,
            'semantic' => $this->semantic,
            'opacity' => $this->opacity,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $string = static function (string $key) use ($serialized): ?string {
            $value = $serialized[$key] ?? null;
            if ($value === null) {
                return null;
            }
            if (! is_string($value)) {
                throw new InvalidArgumentException("Serialized style field '{$key}' must be a string or null.");
            }

            return $value;
        };
        $opacity = $serialized['opacity'] ?? 1.0;
        if (! is_int($opacity) && ! is_float($opacity)) {
            throw new InvalidArgumentException('Serialized style opacity must be numeric.');
        }

        return new self(
            $string('fill'),
            $string('text'),
            $string('type_role'),
            $string('stroke'),
            $string('stroke_width'),
            $string('radius'),
            $string('padding'),
            $string('semantic'),
            (float) $opacity,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
