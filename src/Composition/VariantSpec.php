<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A derived output shape for a channel, aspect ratio, or accessibility need.
 *
 * A variant does not replace the scene it came from. It names a new canvas and the minimum
 * set of overrides needed to make the same content read well there: which stacks change axis,
 * and how they align across it. The source scene stays canonical and editable, and no copy is
 * duplicated, so the two outputs cannot drift apart in content.
 */
final readonly class VariantSpec implements JsonSerializable
{
    /** @var array<string,StackDirection> */
    public array $stackDirections;

    /** @var array<string,Alignment> */
    public array $stackAlignments;

    /**
     * @param  array<string,StackDirection>  $stackDirections
     * @param  array<string,Alignment>  $stackAlignments
     */
    public function __construct(
        public string $id,
        public Dimensions $canvas,
        array $stackDirections = [],
        array $stackAlignments = [],
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
        foreach ($stackAlignments as $nodeId => $alignment) {
            if (! is_string($nodeId)) {
                throw new InvalidArgumentException("Variant '{$id}' keys alignment overrides by node identifier.");
            }
            new NodeId($nodeId);
            if (! $alignment instanceof Alignment) {
                throw new InvalidArgumentException("Variant '{$id}' alignment override for '{$nodeId}' must be an Alignment.");
            }
        }
        if ($padding !== null && preg_match('/^[a-z0-9][a-z0-9-]*$/', $padding) !== 1) {
            throw new InvalidArgumentException("Variant '{$id}' padding must be a brand spacing step.");
        }
        ksort($stackDirections);
        ksort($stackAlignments);
        $this->stackDirections = $stackDirections;
        $this->stackAlignments = $stackAlignments;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $directions = [];
        foreach ($this->stackDirections as $nodeId => $direction) {
            $directions[$nodeId] = $direction->value;
        }

        $alignments = [];
        foreach ($this->stackAlignments as $nodeId => $alignment) {
            $alignments[$nodeId] = $alignment->value;
        }

        return [
            'id' => $this->id,
            'canvas' => $this->canvas->toArray(),
            'stack_directions' => (object) $directions,
            'stack_alignments' => (object) $alignments,
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
        $alignments = $serialized['stack_alignments'] ?? [];
        $padding = $serialized['padding'] ?? null;
        $label = $serialized['label'] ?? null;
        if (! is_string($id) || ! is_array($canvas) || ! is_array($directions) || ! is_array($alignments)) {
            throw new InvalidArgumentException('Serialized variants require id, canvas, stack_directions, and stack_alignments.');
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

        $decodedAlignments = [];
        foreach ($alignments as $nodeId => $alignment) {
            if (! is_string($alignment)) {
                throw new InvalidArgumentException('Serialized stack alignments must be strings.');
            }
            $decodedAlignments[(string) $nodeId] = Alignment::tryFrom($alignment)
                ?? throw new InvalidArgumentException("Variant '{$id}' declares an unsupported stack alignment.");
        }

        return new self($id, Dimensions::fromArray($canvas), $decoded, $decodedAlignments, $padding, $label);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
