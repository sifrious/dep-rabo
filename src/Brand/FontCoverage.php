<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

/**
 * Which characters a font file can actually draw.
 *
 * Reads the `cmap` table directly, because the alternative is discovering at publication time that
 * the one glyph an argument turns on renders as an empty box. The brand's faces here are latin
 * subsets: they carry about 230 characters and none of them is U+2260. A composition whose headline
 * is "Agent completion ≠ verified completion" therefore gets that character from a system font on
 * every machine that happens to have one, and from nothing at all on a machine that does not.
 *
 * Only formats 4 and 12 are read. Those cover every modern font; a subtable this cannot parse is
 * reported as covering nothing rather than as covering everything, so the failure direction is a
 * false warning rather than a false pass.
 */
final readonly class FontCoverage
{
    /** @param array<int,true> $codepoints */
    private function __construct(private array $codepoints) {}

    public static function ofTrueType(string $bytes): self
    {
        $offset = self::tableOffset($bytes, 'cmap');
        if ($offset === null) {
            return new self([]);
        }

        $subtable = self::bestSubtable($bytes, $offset);
        if ($subtable === null) {
            return new self([]);
        }

        return new self(match (self::uint16($bytes, $subtable)) {
            4 => self::readFormat4($bytes, $subtable),
            12 => self::readFormat12($bytes, $subtable),
            default => [],
        });
    }

    public function covers(int $codepoint): bool
    {
        return isset($this->codepoints[$codepoint]);
    }

    public function count(): int
    {
        return count($this->codepoints);
    }

    /** Characters in the text that this font cannot draw, in first-seen order. */
    /** @return list<string> */
    public function missingFrom(string $text): array
    {
        $missing = [];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $codepoint = mb_ord($character, 'UTF-8');
            if ($codepoint === false || $codepoint === 0x20 || $this->covers($codepoint)) {
                continue;
            }
            $missing[$character] = $character;
        }

        return array_values($missing);
    }

    private static function tableOffset(string $bytes, string $tag): ?int
    {
        if (strlen($bytes) < 12) {
            return null;
        }
        $tables = self::uint16($bytes, 4);
        for ($i = 0; $i < $tables; $i++) {
            $record = 12 + $i * 16;
            if (strlen($bytes) < $record + 16) {
                return null;
            }
            if (substr($bytes, $record, 4) === $tag) {
                return self::uint32($bytes, $record + 8);
            }
        }

        return null;
    }

    private static function bestSubtable(string $bytes, int $cmap): ?int
    {
        $count = self::uint16($bytes, $cmap + 2);
        $best = null;
        for ($i = 0; $i < $count; $i++) {
            $record = $cmap + 4 + $i * 8;
            $platform = self::uint16($bytes, $record);
            $encoding = self::uint16($bytes, $record + 2);
            $isUnicode = ($platform === 3 && ($encoding === 1 || $encoding === 10))
                || ($platform === 0 && ($encoding === 3 || $encoding === 4));
            if ($isUnicode) {
                $best = $cmap + self::uint32($bytes, $record + 4);
            }
        }

        return $best;
    }

    /** @return array<int,true> */
    private static function readFormat4(string $bytes, int $subtable): array
    {
        $segmentBytes = self::uint16($bytes, $subtable + 6);
        $segments = intdiv($segmentBytes, 2);
        $endBase = $subtable + 14;
        $startBase = $endBase + $segmentBytes + 2;

        $codepoints = [];
        for ($i = 0; $i < $segments; $i++) {
            $end = self::uint16($bytes, $endBase + $i * 2);
            $start = self::uint16($bytes, $startBase + $i * 2);
            if ($end === 0xFFFF || $start > $end) {
                continue;
            }
            for ($code = $start; $code <= $end; $code++) {
                $codepoints[$code] = true;
            }
        }

        return $codepoints;
    }

    /** @return array<int,true> */
    private static function readFormat12(string $bytes, int $subtable): array
    {
        $groups = self::uint32($bytes, $subtable + 12);
        $codepoints = [];
        for ($i = 0; $i < $groups; $i++) {
            $group = $subtable + 16 + $i * 12;
            $start = self::uint32($bytes, $group);
            $end = self::uint32($bytes, $group + 4);
            // A malformed or enormous range must not hang validation.
            $end = min($end, $start + 0x2000);
            for ($code = $start; $code <= $end; $code++) {
                $codepoints[$code] = true;
            }
        }

        return $codepoints;
    }

    private static function uint16(string $bytes, int $offset): int
    {
        $value = unpack('n', substr($bytes, $offset, 2));

        return $value === false ? 0 : $value[1];
    }

    private static function uint32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));

        return $value === false ? 0 : $value[1];
    }
}
