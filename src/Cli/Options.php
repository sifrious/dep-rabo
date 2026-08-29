<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Cli;

use InvalidArgumentException;

/** Minimal `--key=value` and `--flag` parsing. No dependency earns its keep for this. */
final readonly class Options
{
    /**
     * @param  list<string>  $positional
     * @param  array<string,string>  $values
     * @param  list<string>  $flags
     */
    private function __construct(
        private array $positional,
        private array $values,
        private array $flags,
    ) {}

    /** @param list<string> $arguments */
    public static function parse(array $arguments): self
    {
        $positional = [];
        $values = [];
        $flags = [];

        foreach ($arguments as $argument) {
            if (! str_starts_with($argument, '--')) {
                $positional[] = $argument;

                continue;
            }
            $body = substr($argument, 2);
            if (str_contains($body, '=')) {
                [$key, $value] = explode('=', $body, 2);
                $values[$key] = $value;

                continue;
            }
            $flags[] = $body;
        }

        return new self($positional, $values, $flags);
    }

    public function positional(int $index, string $expected): string
    {
        return $this->positional[$index] ?? throw new InvalidArgumentException("Expected {$expected} as argument ".($index + 1).'.');
    }

    public function value(string $key, string $default): string
    {
        return $this->values[$key] ?? $default;
    }

    public function flag(string $key): bool
    {
        return in_array($key, $this->flags, true);
    }
}
