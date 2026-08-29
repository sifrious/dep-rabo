<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An explicit Brand Library version.
 *
 * Compatibility is declared, not guessed: a composition pinned to major 1 may be rendered
 * by any 1.x library, and never by 2.x. Nothing else about the version string carries
 * meaning to Rabo.
 */
final readonly class BrandVersion implements JsonSerializable, Stringable
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
    ) {
        if ($major < 0 || $minor < 0 || $patch < 0) {
            throw new InvalidArgumentException('Brand version numbers must not be negative.');
        }
    }

    public static function parse(string $version): self
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches) !== 1) {
            throw new InvalidArgumentException('Brand versions must be major.minor.patch.');
        }

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    /** A library satisfies a pinned version when its major matches and it is no older. */
    public function satisfies(self $pinned): bool
    {
        if ($this->major !== $pinned->major) {
            return false;
        }

        return [$this->minor, $this->patch] >= [$pinned->minor, $pinned->patch];
    }

    public function equals(self $other): bool
    {
        return $this->__toString() === $other->__toString();
    }

    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }
}
