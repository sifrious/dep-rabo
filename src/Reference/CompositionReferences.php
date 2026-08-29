<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Reference;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * What a composition points at, without owning any of it.
 *
 * This is the answer to "what treatment caused this to exist", "what evidence supports it",
 * "what approved it", and "where did it go" — held as opaque references that only their
 * owning package can resolve. A reference is not permission to read the thing it names, and
 * holding one here never makes Rabo the system of record for it.
 */
final readonly class CompositionReferences implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.composition-references';

    public const CONTRACT_VERSION = 1;

    /** @var array<string,list<CrossPackageReference>> */
    public array $byRole;

    /** @param array<string,list<CrossPackageReference>> $byRole */
    public function __construct(array $byRole = [])
    {
        $normalised = [];
        foreach ($byRole as $roleName => $references) {
            $role = ReferenceRole::tryFrom((string) $roleName)
                ?? throw new InvalidArgumentException("Rabo has no reference role '{$roleName}'.");
            if (! is_array($references)) {
                throw new InvalidArgumentException("References for role '{$role->value}' must be a list.");
            }
            $references = array_values($references);
            if ($references === []) {
                continue;
            }
            if (! $role->allowsMany() && count($references) > 1) {
                throw new InvalidArgumentException("Reference role '{$role->value}' accepts a single reference.");
            }
            foreach ($references as $reference) {
                if (! $reference instanceof CrossPackageReference) {
                    throw new InvalidArgumentException("Reference role '{$role->value}' accepts CrossPackageReference values only.");
                }
                if (! in_array($reference->owner, $role->owners(), true)) {
                    throw new InvalidArgumentException("Reference role '{$role->value}' may not be owned by '{$reference->owner}'.");
                }
            }
            $normalised[$role->value] = $references;
        }
        ksort($normalised);
        $this->byRole = $normalised;
    }

    public function has(ReferenceRole $role): bool
    {
        return ($this->byRole[$role->value] ?? []) !== [];
    }

    /** @return list<CrossPackageReference> */
    public function all(ReferenceRole $role): array
    {
        return $this->byRole[$role->value] ?? [];
    }

    public function one(ReferenceRole $role): ?CrossPackageReference
    {
        return $this->byRole[$role->value][0] ?? null;
    }

    /** Required roles that carry nothing. Explicitly empty rather than silently absent. */
    /** @return list<ReferenceRole> */
    public function missingRequired(): array
    {
        $missing = [];
        foreach (ReferenceRole::cases() as $role) {
            if ($role->isRequired() && ! $this->has($role)) {
                $missing[] = $role;
            }
        }

        return $missing;
    }

    public function with(ReferenceRole $role, CrossPackageReference ...$references): self
    {
        $byRole = $this->byRole;
        $byRole[$role->value] = $role->allowsMany()
            ? [...($byRole[$role->value] ?? []), ...$references]
            : array_values($references);

        return new self($byRole);
    }

    /**
     * The canonical serialization.
     *
     * Roles are emitted as a JSON object, so compare on this rather than on toArray(): two
     * equal envelopes hold distinct stdClass maps and are never identical by ===.
     */
    public function canonical(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function equals(self $other): bool
    {
        return $this->canonical() === $other->canonical();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $roles = [];
        foreach ($this->byRole as $role => $references) {
            $roles[$role] = array_map(static fn (CrossPackageReference $r): array => $r->toArray(), $references);
        }

        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'roles' => (object) $roles,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo composition reference contract.');
        }
        $roles = $serialized['roles'] ?? [];
        if (! is_array($roles)) {
            throw new InvalidArgumentException('Serialized composition references require a roles object.');
        }
        $decoded = [];
        foreach ($roles as $role => $references) {
            if (! is_array($references)) {
                throw new InvalidArgumentException("Serialized references for '{$role}' must be a list.");
            }
            $decoded[(string) $role] = array_map(static function (mixed $reference): CrossPackageReference {
                if (! is_array($reference)) {
                    throw new InvalidArgumentException('Serialized references must be objects.');
                }

                return CrossPackageReference::fromArray($reference);
            }, array_values($references));
        }

        return new self($decoded);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
