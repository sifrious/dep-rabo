<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A derived output shape for a channel, aspect ratio, or accessibility need.
 *
 * A variant does not replace the scene it came from. It names a new canvas and the minimum
 * set of overrides needed to make the same content read well there — for the first proof,
 * flipping one stack's axis. The source scene stays canonical and editable.
 */
final readonly class VariantSpec implements JsonSerializable
{
    /** @var array<string,StackDirection> */
    public array $stackDirections;

    /** @param array<string,StackDirection> $stackDirections */
    public function __construct(
        public string $id,
        public Dimensions $canvas,
        array $stackDirections = [],
        public ?string $padding = null,
        public ?string $label = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Variant identifiers must be lowercase kebab-case.');
        }
        foreach ($stackDirections as $nodeId => $direction) {
            if (! is_string($nodeId)) {
                throw new InvalidArgumentException("Variant '{$id}' keys stack overrides by node identifier.");
            }
            new NodeId($nodeId);
            if (! $direction instanceof StackDirection) {
                throw new InvalidArgumentException("Variant '{$id}' override for '{$nodeId}' must be a StackDirection.");
            }
        }
        if ($padding !== null && preg_match('/^[a-z0-9][a-z0-9-]*$/', $padding) !== 1) {
            throw new InvalidArgumentException("Variant '{$id}' padding must be a brand spacing step.");
        }
        ksort($stackDirections);
        $this->stackDirections = $stackDirections;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $directions = [];
        foreach ($this->stackDirections as $nodeId => $direction) {
            $directions[$nodeId] = $direction->value;
        }

        return [
            'id' => $this->id,
            'canvas' => $this->canvas->toArray(),
            'stack_directions' => (object) $directions,
            'padding' => $this->padding,
            'label' => $this->label,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $canvas = $serialized['canvas'] ?? null;
        $directions = $serialized['stack_directions'] ?? [];
        $padding = $serialized['padding'] ?? null;
        $label = $serialized['label'] ?? null;
        if (! is_string($id) || ! is_array($canvas) || ! is_array($directions)) {
            throw new InvalidArgumentException('Serialized variants require id, canvas, and stack_directions.');
        }
        if ($padding !== null && ! is_string($padding)) {
            throw new InvalidArgumentException('Serialized variant padding must be a string or null.');
        }
        if ($label !== null && ! is_string($label)) {
            throw new InvalidArgumentException('Serialized variant labels must be a string or null.');
        }
        $decoded = [];
        foreach ($directions as $nodeId => $direction) {
            if (! is_string($direction)) {
                throw new InvalidArgumentException('Serialized stack directions must be strings.');
            }
            $decoded[(string) $nodeId] = StackDirection::tryFrom($direction)
                ?? throw new InvalidArgumentException("Variant '{$id}' declares an unsupported stack direction.");
        }

        return new self($id, Dimensions::fromArray($canvas), $decoded, $padding, $label);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
