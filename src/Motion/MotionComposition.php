<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Composition;

/**
 * A timeline over an existing composition, plus its mandatory accessible alternative.
 *
 * Motion is not an MP4 attached to a scene. It is a declared sequence over the same nodes the
 * still composition already contains, which is why the moving version and the still version
 * cannot say different things: there is only one set of words, laid out once.
 *
 * The reduced-motion variant is required rather than optional, and is derived from this same
 * timeline, so it cannot be forgotten and cannot drift.
 */
final readonly class MotionComposition implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.motion-composition';

    public const CONTRACT_VERSION = 1;

    public function __construct(
        public string $id,
        public int $version,
        public string $compositionId,
        public string $scene,
        public Timeline $timeline,
        public ReducedMotionStrategy $reducedMotion,
        public ?string $description = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Motion composition identifiers must be lowercase kebab-case.');
        }
        if ($version < 1) {
            throw new InvalidArgumentException('Motion composition versions start at 1.');
        }
    }

    public function appliesTo(Composition $composition): bool
    {
        return $composition->id === $this->compositionId
            && array_key_exists($this->scene, $composition->allScenes());
    }

    /**
     * The timeline a reduced-motion viewer receives.
     *
     * Derived from the source timeline rather than authored separately. `FinalState` collapses
     * to the composed end state with no cues at all; `CrossFadeOnly` keeps the sequence but
     * removes movement by shortening every cue to the brand's fast duration.
     */
    public function reducedMotionTimeline(BrandLibrary $brand): Timeline
    {
        return match ($this->reducedMotion) {
            ReducedMotionStrategy::FinalState => new Timeline($this->timeline->duration, []),
            ReducedMotionStrategy::CrossFadeOnly, ReducedMotionStrategy::StaticSequence => new Timeline(
                $this->timeline->duration,
                array_map(
                    static fn (Track $track): Track => new Track($track->name, array_map(
                        static fn (Cue $cue): Cue => new Cue(
                            $cue->id,
                            $cue->node,
                            $cue->effect === CueEffect::Emphasise ? CueEffect::Reveal : $cue->effect,
                            $cue->start,
                            new Duration($brand->motion->hasDuration('fast') ? $brand->motion->durationMs('fast') : 1),
                            Easing::Linear,
                            $cue->caption,
                        ),
                        $track->cues,
                    )),
                    $this->timeline->tracks,
                ),
            ),
        };
    }

    /** Captions in playback order, as a text equivalent for the whole piece. */
    /** @return list<string> */
    public function captions(): array
    {
        $captions = [];
        foreach ($this->timeline->cues() as $cue) {
            if ($cue->caption !== null && trim($cue->caption) !== '') {
                $captions[] = $cue->caption;
            }
        }

        return $captions;
    }

    public function key(): string
    {
        return hash('sha256', $this->canonical());
    }

    public function canonical(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'id' => $this->id,
            'version' => $this->version,
            'composition_id' => $this->compositionId,
            'scene' => $this->scene,
            'description' => $this->description,
            'reduced_motion' => $this->reducedMotion->value,
            'timeline' => $this->timeline->toArray(),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo motion composition contract.');
        }
        $id = $serialized['id'] ?? null;
        $version = $serialized['version'] ?? null;
        $compositionId = $serialized['composition_id'] ?? null;
        $scene = $serialized['scene'] ?? null;
        $reduced = $serialized['reduced_motion'] ?? null;
        $timeline = $serialized['timeline'] ?? null;
        $description = $serialized['description'] ?? null;
        if (! is_string($id) || ! is_int($version) || ! is_string($compositionId) || ! is_string($scene) || ! is_string($reduced) || ! is_array($timeline)) {
            throw new InvalidArgumentException('Serialized motion compositions require id, version, composition_id, scene, reduced_motion, and timeline.');
        }
        if ($description !== null && ! is_string($description)) {
            throw new InvalidArgumentException('Serialized motion descriptions must be a string or null.');
        }

        return new self(
            $id,
            $version,
            $compositionId,
            $scene,
            Timeline::fromArray($timeline),
            ReducedMotionStrategy::tryFrom($reduced) ?? throw new InvalidArgumentException("Motion composition '{$id}' declares an unsupported reduced-motion strategy."),
            $description,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
