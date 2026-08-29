<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests;

use RuntimeException;

/** Loads the checked-in vertical-slice fixture. Tests read the same data a reviewer does. */
final class Fixture
{
    public const SLICE = 'agent-completion-verified-completion';

    public static function path(string $relative = ''): string
    {
        return dirname(__DIR__).'/fixtures/'.self::SLICE.($relative === '' ? '' : '/'.$relative);
    }

    /** @return array<string,mixed> */
    public static function json(string $relative): array
    {
        $raw = file_get_contents(self::path($relative));
        if ($raw === false) {
            throw new RuntimeException("Fixture {$relative} is missing.");
        }
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Fixture {$relative} is not an object.");
        }

        return $decoded;
    }
}
