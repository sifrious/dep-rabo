<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;

/** Declared families and the semantic roles built from them. */
final readonly class TypographySystem implements JsonSerializable
{
    /** @var array<string,FontFamily> */
    public array $families;

    /** @var array<string,TypeRole> */
    public array $roles;

    /**
     * @param  list<FontFamily>  $families
     * @param  list<TypeRole>  $roles
     */
    public function __construct(array $families, array $roles)
    {
        $indexedFamilies = [];
        foreach ($families as $family) {
            if (! $family instanceof FontFamily) {
                throw new InvalidArgumentException('Typography systems accept FontFamily values only.');
            }
            if (isset($indexedFamilies[$family->name])) {
                throw new InvalidArgumentException("Font family '{$family->name}' is declared twice.");
            }
            $indexedFamilies[$family->name] = $family;
        }
        ksort($indexedFamilies);

        $indexedRoles = [];
        foreach ($roles as $role) {
            if (! $role instanceof TypeRole) {
                throw new InvalidArgumentException('Typography systems accept TypeRole values only.');
            }
            if (isset($indexedRoles[$role->name])) {
                throw new InvalidArgumentException("Type role '{$role->name}' is declared twice.");
            }
            $family = $indexedFamilies[$role->family]
                ?? throw new InvalidArgumentException("Type role '{$role->name}' references undeclared family '{$role->family}'.");
            if (! $family->supportsWeight($role->weight)) {
                throw new InvalidArgumentException("Type role '{$role->name}' requests weight {$role->weight}, which '{$role->family}' does not declare.");
            }
            $indexedRoles[$role->name] = $role;
        }
        ksort($indexedRoles);

        $this->families = $indexedFamilies;
        $this->roles = $indexedRoles;
    }

    public function hasRole(string $name): bool
    {
        return isset($this->roles[$name]);
    }

    public function role(string $name): TypeRole
    {
        return $this->roles[$name] ?? throw new UnknownBrandToken("The brand declares no type role '{$name}'.");
    }

    public function family(string $name): FontFamily
    {
        return $this->families[$name] ?? throw new UnknownBrandToken("The brand declares no font family '{$name}'.");
    }

    /** Estimated rendered width, using the brand's declared advance ratio. */
    public function estimateWidthPx(string $roleName, string $text): float
    {
        $role = $this->role($roleName);
        $ratio = $this->family($role->family)->advanceRatio($role->weight);
        $characters = mb_strlen($text, 'UTF-8');
        $trackingPx = $characters > 0 ? ($characters - 1) * $role->tracking * $role->sizePx : 0.0;

        return $characters * $ratio * $role->sizePx + $trackingPx;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'families' => array_values(array_map(static fn (FontFamily $f): array => $f->toArray(), $this->families)),
            'roles' => array_values(array_map(static fn (TypeRole $r): array => $r->toArray(), $this->roles)),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $families = $serialized['families'] ?? null;
        $roles = $serialized['roles'] ?? null;
        if (! is_array($families) || ! is_array($roles)) {
            throw new InvalidArgumentException('Serialized typography systems require families and roles.');
        }

        return new self(
            array_map(static function (mixed $f): FontFamily {
                if (! is_array($f)) {
                    throw new InvalidArgumentException('Serialized font families must be objects.');
                }

                return FontFamily::fromArray($f);
            }, array_values($families)),
            array_map(static function (mixed $r): TypeRole {
                if (! is_array($r)) {
                    throw new InvalidArgumentException('Serialized type roles must be objects.');
                }

                return TypeRole::fromArray($r);
            }, array_values($roles)),
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
