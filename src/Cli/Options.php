<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Cli;

use InvalidArgumentException;

/**
 * Minimal `--key=value` and `--flag` parsing. No dependency earns its keep for this.
 *
 * Every command declares the options it accepts, and anything else is refused. That is not
 * pedantry: `--no-embed-fonts` was documented in three places and read by nothing, so it silently
 * did the opposite of what the page promised, and a parser that accepted any `--word` is what let
 * that sit. A misspelled option is the CLI's version of the false pass every validation rule here
 * exists to prevent.
 */
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

    /**
     * @param  list<string>  $arguments
     * @param  list<string>  $keys  accepted `--key=value` names
     * @param  list<string>  $flags  accepted `--flag` names
     */
    public static function parse(array $arguments, array $keys, array $flags): self
    {
        $positional = [];
        $values = [];
        $given = [];

        foreach ($arguments as $argument) {
            if (! str_starts_with($argument, '--')) {
                $positional[] = $argument;

                continue;
            }
            $body = substr($argument, 2);
            if (str_contains($body, '=')) {
                [$key, $value] = explode('=', $body, 2);
                if (! in_array($key, $keys, true)) {
                    throw new InvalidArgumentException(self::unknown($key, $keys, $flags, in_array($key, $flags, true)
                        ? "'--{$key}' is a flag and takes no value."
                        : null));
                }
                $values[$key] = $value;

                continue;
            }
            if (! in_array($body, $flags, true)) {
                throw new InvalidArgumentException(self::unknown($body, $keys, $flags, in_array($body, $keys, true)
                    ? "'--{$body}' needs a value, as --{$body}=<value>."
                    : null));
            }
            $given[] = $body;
        }

        return new self($positional, $values, $given);
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $flags
     */
    private static function unknown(string $name, array $keys, array $flags, ?string $because): string
    {
        $accepted = [
            ...array_map(static fn (string $key): string => "--{$key}=<value>", $keys),
            ...array_map(static fn (string $flag): string => "--{$flag}", $flags),
        ];
        sort($accepted);

        return ($because ?? "Unknown option '--{$name}'.")
            .' This command accepts '.($accepted === [] ? 'no options.' : implode(', ', $accepted).'.');
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
