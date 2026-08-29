<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Asset;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A content digest over exact bytes.
 *
 * This is the whole of an asset's identity. A path is a place bytes happen to sit; it is
 * not a name for them. Identical bytes always produce this same value, and any change to
 * the bytes produces a different one.
 */
final readonly class ContentDigest implements JsonSerializable, Stringable
{
    public const ALGORITHM = 'sha256';

    public function __construct(public string $hex)
    {
        if (preg_match('/^[0-9a-f]{64}$/', $hex) !== 1) {
            throw new InvalidArgumentException('Content digests must be 64 lowercase hexadecimal characters.');
        }
    }

    public static function ofBytes(string $bytes): self
    {
        return new self(hash(self::ALGORITHM, $bytes));
    }

    public static function parse(string $value): self
    {
        $prefix = self::ALGORITHM.':';
        if (! str_starts_with($value, $prefix)) {
            throw new InvalidArgumentException('Content digests must be prefixed with their algorithm.');
        }

        return new self(substr($value, strlen($prefix)));
    }

    public function matches(string $bytes): bool
    {
        return hash_equals($this->hex, hash(self::ALGORITHM, $bytes));
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->hex, $other->hex);
    }

    public function __toString(): string
    {
        return self::ALGORITHM.':'.$this->hex;
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }
}
