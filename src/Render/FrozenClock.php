<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use DateTimeImmutable;
use DateTimeZone;

/** A fixed instant, for golden fixtures and tests. */
final readonly class FrozenClock implements Clock
{
    private DateTimeImmutable $instant;

    public function __construct(string $instant = '2026-08-29T00:00:00+00:00')
    {
        $this->instant = new DateTimeImmutable($instant, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
