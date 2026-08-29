<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use DateTimeImmutable;

/**
 * The time a render happened.
 *
 * Injected rather than read from the system so that a golden artifact can be regenerated
 * byte for byte. A timestamp baked into an output would make every render differ from every
 * other one and destroy the reproducibility this package exists to demonstrate.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
