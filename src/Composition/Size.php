<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

use InvalidArgumentException;
use JsonSerializable;

/** An intrinsic node size in scene units. */
final readonly class Size implements JsonSerializable
{
    public function __construct(public float $width, public float $height)
    {
        if ($width < 0.0 || $height < 0.0) {
            throw new InvalidArgumentException('Sizes must not be negative.');
        }
    }

    /** @return array{width:float,height:float} */
    public function toArray(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $width = $serialized['width'] ?? null;
        $height = $serialized['height'] ?? null;
        if ((! is_int($width) && ! is_float($width)) || (! is_int($height) && ! is_float($height))) {
            throw new InvalidArgumentException('Serialized sizes require numeric width and height.');
        }

        return new self((float) $width, (float) $height);
    }

    /** @return array{width:float,height:float} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
