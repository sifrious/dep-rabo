<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

use InvalidArgumentException;
use JsonSerializable;

/** A named lane of cues, so a timeline reads as beats rather than a flat list. */
final readonly class Track implements JsonSerializable
{
    /** @var list<Cue> */
    public array $cues;

    /** @param list<Cue> $cues */
    public function __construct(public string $name, array $cues)
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException('Track names must be lowercase kebab-case.');
        }
        $sorted = array_values($cues);
        foreach ($sorted as $cue) {
            if (! $cue instanceof Cue) {
                throw new InvalidArgumentException("Track '{$name}' accepts Cue values only.");
            }
        }
        usort($sorted, static fn (Cue $a, Cue $b): int => [$a->start->milliseconds, $a->id] <=> [$b->start->milliseconds, $b->id]);
        $this->cues = $sorted;
    }

    public function end(): Duration
    {
        $end = new Duration(0);
        foreach ($this->cues as $cue) {
            if ($cue->end()->milliseconds > $end->milliseconds) {
                $end = $cue->end();
            }
        }

        return $end;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'cues' => array_map(static fn (Cue $c): array => $c->toArray(), $this->cues)];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $name = $serialized['name'] ?? null;
        $cues = $serialized['cues'] ?? null;
        if (! is_string($name) || ! is_array($cues)) {
            throw new InvalidArgumentException('Serialized tracks require a name and cues.');
        }

        return new self($name, array_map(static function (mixed $c): Cue {
            if (! is_array($c)) {
                throw new InvalidArgumentException('Serialized cues must be objects.');
            }

            return Cue::fromArray($c);
        }, array_values($cues)));
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
