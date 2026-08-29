<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Composition\NodeId;

/**
 * One timed change to one node.
 *
 * A cue names the node it affects, when it starts, how long it takes, and what it does. It
 * does not describe pixels: the scene already says what the node looks like, and a cue that
 * restated that could contradict it.
 */
final readonly class Cue implements JsonSerializable
{
    public function __construct(
        public string $id,
        public NodeId $node,
        public CueEffect $effect,
        public Duration $start,
        public Duration $duration,
        public Easing $easing = Easing::Quiet,
        public ?string $caption = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Cue identifiers must be lowercase kebab-case.');
        }
        if ($duration->isZero()) {
            throw new InvalidArgumentException("Cue '{$id}' must have a duration.");
        }
    }

    public function end(): Duration
    {
        return $this->start->plus($this->duration);
    }

    public function overlaps(self $other): bool
    {
        if (! $this->node->equals($other->node)) {
            return false;
        }

        return $this->start->milliseconds < $other->end()->milliseconds
            && $other->start->milliseconds < $this->end()->milliseconds;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'node' => $this->node->value,
            'effect' => $this->effect->value,
            'start_ms' => $this->start->milliseconds,
            'duration_ms' => $this->duration->milliseconds,
            'easing' => $this->easing->value,
            'caption' => $this->caption,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $node = $serialized['node'] ?? null;
        $effect = $serialized['effect'] ?? null;
        $start = $serialized['start_ms'] ?? null;
        $duration = $serialized['duration_ms'] ?? null;
        $easing = $serialized['easing'] ?? Easing::Quiet->value;
        $caption = $serialized['caption'] ?? null;
        if (! is_string($id) || ! is_string($node) || ! is_string($effect) || ! is_int($start) || ! is_int($duration) || ! is_string($easing)) {
            throw new InvalidArgumentException('Serialized cues require id, node, effect, start_ms, duration_ms, and easing.');
        }
        if ($caption !== null && ! is_string($caption)) {
            throw new InvalidArgumentException('Serialized cue captions must be a string or null.');
        }

        return new self(
            $id,
            new NodeId($node),
            CueEffect::tryFrom($effect) ?? throw new InvalidArgumentException("Cue '{$id}' declares an unsupported effect."),
            new Duration($start),
            new Duration($duration),
            Easing::tryFrom($easing) ?? throw new InvalidArgumentException("Cue '{$id}' declares an unsupported easing."),
            $caption,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
