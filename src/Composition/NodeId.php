<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A stable identity for a node inside a composition.
 *
 * Identity is what makes a composition editable and a validation issue addressable: an
 * issue points at `headline`, not at "the third text element". Derived variants reuse the
 * same identifiers so a node can be followed across aspect ratios.
 */
final readonly class NodeId implements JsonSerializable, Stringable
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException('Node identifiers must be lowercase kebab-case.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
