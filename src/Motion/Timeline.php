<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Composition\NodeId;

/**
 * The ordered set of cues that make a scene move.
 *
 * Ordering is derived, never authored: cues sort by start time then identifier, so two
 * timelines built from the same cues in different orders are the same timeline. That is what
 * lets a motion artifact be byte-reproducible.
 */
final readonly class Timeline implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.timeline';

    public const CONTRACT_VERSION = 1;

    /** @var list<Track> */
    public array $tracks;

    /** @param list<Track> $tracks */
    public function __construct(public Duration $duration, array $tracks)
    {
        if ($duration->isZero()) {
            throw new InvalidArgumentException('A timeline must have a duration.');
        }
        $sorted = array_values($tracks);
        $seen = [];
        foreach ($sorted as $track) {
            if (! $track instanceof Track) {
                throw new InvalidArgumentException('Timelines accept Track values only.');
            }
            if (isset($seen[$track->name])) {
                throw new InvalidArgumentException("Track '{$track->name}' is declared twice.");
            }
            $seen[$track->name] = true;
        }
        usort($sorted, static fn (Track $a, Track $b): int => $a->name <=> $b->name);
        $this->tracks = $sorted;
    }

    /** Every cue across every track, in deterministic playback order. */
    /** @return list<Cue> */
    public function cues(): array
    {
        $cues = [];
        foreach ($this->tracks as $track) {
            foreach ($track->cues as $cue) {
                $cues[] = $cue;
            }
        }
        usort($cues, static fn (Cue $a, Cue $b): int => [$a->start->milliseconds, $a->id] <=> [$b->start->milliseconds, $b->id]);

        return $cues;
    }

    /** Cues that run past the end of the timeline. */
    /** @return list<Cue> */
    public function cuesBeyondEnd(): array
    {
        return array_values(array_filter(
            $this->cues(),
            fn (Cue $cue): bool => $cue->end()->milliseconds > $this->duration->milliseconds,
        ));
    }

    /** Pairs of cues that touch the same node at the same time with no stated winner. */
    /** @return list<array{0:Cue,1:Cue}> */
    public function conflicts(): array
    {
        $cues = $this->cues();
        $conflicts = [];
        for ($i = 0; $i < count($cues); $i++) {
            for ($j = $i + 1; $j < count($cues); $j++) {
                if ($cues[$i]->overlaps($cues[$j])) {
                    $conflicts[] = [$cues[$i], $cues[$j]];
                }
            }
        }

        return $conflicts;
    }

    /** Whether a node is on screen when the timeline ends. */
    public function nodeVisibleAtEnd(NodeId $node): bool
    {
        $visible = false;
        $touched = false;
        foreach ($this->cues() as $cue) {
            if (! $cue->node->equals($node)) {
                continue;
            }
            $touched = true;
            $visible = $cue->effect->leavesNodeVisible();
        }

        return $touched ? $visible : true;
    }

    /** @return list<NodeId> */
    public function nodes(): array
    {
        $nodes = [];
        foreach ($this->cues() as $cue) {
            $nodes[$cue->node->value] = $cue->node;
        }
        ksort($nodes);

        return array_values($nodes);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'duration_ms' => $this->duration->milliseconds,
            'tracks' => array_map(static fn (Track $t): array => $t->toArray(), $this->tracks),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo timeline contract.');
        }
        $duration = $serialized['duration_ms'] ?? null;
        $tracks = $serialized['tracks'] ?? null;
        if (! is_int($duration) || ! is_array($tracks)) {
            throw new InvalidArgumentException('Serialized timelines require duration_ms and tracks.');
        }

        return new self(new Duration($duration), array_map(static function (mixed $t): Track {
            if (! is_array($t)) {
                throw new InvalidArgumentException('Serialized tracks must be objects.');
            }

            return Track::fromArray($t);
        }, array_values($tracks)));
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
