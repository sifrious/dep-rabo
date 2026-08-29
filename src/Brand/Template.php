<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Composition\Dimensions;

/**
 * A reusable composition shape the brand offers.
 *
 * A template names an intended canvas and the roles a composition built from it is expected
 * to fill. It is a starting point, not a constraint the renderer enforces — Rabo is not a
 * layout engine and a composition may depart from its template.
 */
final readonly class Template implements JsonSerializable
{
    /** @var list<string> */
    public array $requiredRoles;

    /** @param list<string> $requiredRoles */
    public function __construct(
        public string $id,
        public TemplateKind $kind,
        public string $label,
        public Dimensions $canvas,
        array $requiredRoles = [],
        public ?string $description = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Template identifiers must be lowercase kebab-case.');
        }
        foreach ($requiredRoles as $role) {
            if (! is_string($role) || preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $role) !== 1) {
                throw new InvalidArgumentException("Template '{$id}' lists a required role that is not lowercase kebab-case.");
            }
        }
        $this->requiredRoles = array_values($requiredRoles);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'canvas' => $this->canvas->toArray(),
            'required_roles' => $this->requiredRoles,
            'description' => $this->description,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $id = $serialized['id'] ?? null;
        $kind = $serialized['kind'] ?? null;
        $label = $serialized['label'] ?? null;
        $canvas = $serialized['canvas'] ?? null;
        $requiredRoles = $serialized['required_roles'] ?? [];
        $description = $serialized['description'] ?? null;
        if (! is_string($id) || ! is_string($kind) || ! is_string($label) || ! is_array($canvas) || ! is_array($requiredRoles)) {
            throw new InvalidArgumentException('Serialized templates require id, kind, label, canvas, and required_roles.');
        }
        if ($description !== null && ! is_string($description)) {
            throw new InvalidArgumentException('Serialized template descriptions must be a string or null.');
        }

        return new self(
            $id,
            TemplateKind::tryFrom($kind) ?? throw new InvalidArgumentException("Template '{$id}' declares an unsupported kind."),
            $label,
            Dimensions::fromArray($canvas),
            array_values(array_map(strval(...), $requiredRoles)),
            $description,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
