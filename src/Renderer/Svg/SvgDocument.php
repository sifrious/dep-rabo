<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Svg;

/**
 * A small, deterministic SVG writer.
 *
 * Attributes are emitted in the order given, numbers through `%.2F` — the uppercase form,
 * because lowercase `%f` is locale-dependent and would emit a comma decimal separator under
 * some locales, silently producing different bytes on different machines.
 */
final class SvgDocument
{
    /** @var list<string> */
    private array $lines = [];

    public function __construct(private readonly int $indent = 0) {}

    public static function number(float $value): string
    {
        $formatted = sprintf('%.2F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @param array<string,string|float|int|null> $attributes */
    public function open(string $tag, array $attributes = [], int $depth = 1): self
    {
        $this->lines[] = str_repeat('  ', $depth).'<'.$tag.$this->attributes($attributes).'>';

        return $this;
    }

    public function close(string $tag, int $depth = 1): self
    {
        $this->lines[] = str_repeat('  ', $depth).'</'.$tag.'>';

        return $this;
    }

    /** @param array<string,string|float|int|null> $attributes */
    public function void(string $tag, array $attributes = [], int $depth = 1): self
    {
        $this->lines[] = str_repeat('  ', $depth).'<'.$tag.$this->attributes($attributes).'/>';

        return $this;
    }

    /** @param array<string,string|float|int|null> $attributes */
    public function element(string $tag, string $text, array $attributes = [], int $depth = 1): self
    {
        $this->lines[] = str_repeat('  ', $depth).'<'.$tag.$this->attributes($attributes).'>'.self::escape($text).'</'.$tag.'>';

        return $this;
    }

    public function raw(string $line, int $depth = 1): self
    {
        $this->lines[] = str_repeat('  ', $depth).$line;

        return $this;
    }

    public function toString(): string
    {
        return implode("\n", $this->lines)."\n";
    }

    /** @param array<string,string|float|int|null> $attributes */
    private function attributes(array $attributes): string
    {
        $rendered = '';
        foreach ($attributes as $name => $value) {
            if ($value === null) {
                continue;
            }
            $rendered .= ' '.$name.'="'.self::escape(is_float($value) ? self::number($value) : (string) $value).'"';
        }

        return $rendered;
    }
}
