<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Named colours plus the semantic roles that point at them.
 *
 * Compositions reference roles, not raw colours, so a brand can be re-skinned without
 * touching a scene. Every role must resolve; an unresolvable role is a brand error, not a
 * rendering fallback.
 */
final readonly class ColorSystem implements JsonSerializable
{
    /** @var array<string,ColorToken> */
    public array $tokens;

    /** @var array<string,string> */
    public array $roles;

    /**
     * @param  list<ColorToken>  $tokens
     * @param  array<string,string>  $roles
     */
    public function __construct(array $tokens, array $roles)
    {
        $indexed = [];
        foreach ($tokens as $token) {
            if (! $token instanceof ColorToken) {
                throw new InvalidArgumentException('Colour systems accept ColorToken values only.');
            }
            if (isset($indexed[$token->name])) {
                throw new InvalidArgumentException("Colour token '{$token->name}' is declared twice.");
            }
            $indexed[$token->name] = $token;
        }
        ksort($indexed);

        foreach ($roles as $role => $tokenName) {
            if (! is_string($role) || preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $role) !== 1) {
                throw new InvalidArgumentException('Colour role names must be lowercase kebab-case identifiers.');
            }
            if (! is_string($tokenName) || ! isset($indexed[$tokenName])) {
                throw new InvalidArgumentException("Colour role '{$role}' points at undeclared token '".(is_string($tokenName) ? $tokenName : '?')."'.");
            }
        }
        ksort($roles);

        $this->tokens = $indexed;
        $this->roles = $roles;
    }

    public function hasRole(string $role): bool
    {
        return isset($this->roles[$role]);
    }

    public function resolveRole(string $role): ColorToken
    {
        $tokenName = $this->roles[$role] ?? throw new UnknownBrandToken("The brand declares no colour role '{$role}'.");

        return $this->tokens[$tokenName];
    }

    public function token(string $name): ColorToken
    {
        return $this->tokens[$name] ?? throw new UnknownBrandToken("The brand declares no colour token '{$name}'.");
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'tokens' => array_values(array_map(static fn (ColorToken $t): array => $t->toArray(), $this->tokens)),
            'roles' => (object) $this->roles,
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $tokens = $serialized['tokens'] ?? null;
        $roles = $serialized['roles'] ?? null;
        if (! is_array($tokens) || ! is_array($roles)) {
            throw new InvalidArgumentException('Serialized colour systems require tokens and roles.');
        }

        return new self(
            array_map(
                static function (mixed $token): ColorToken {
                    if (! is_array($token)) {
                        throw new InvalidArgumentException('Serialized colour tokens must be objects.');
                    }

                    return ColorToken::fromArray($token);
                },
                array_values($tokens),
            ),
            $roles,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
