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
 * Only formats 4 and 12 are read. Those cover every modern font.
 *
 * `readable` is separate from `count()` on purpose. A font that could not be parsed and a font that
 * genuinely covers nothing both have zero codepoints, and treating them the same let an unreadable
 * file report no glyph problems at all — a silent pass on the exact question this class exists to
 * answer. Callers must ask whether the coverage is readable before trusting that it is empty.
 */
final readonly class FontCoverage
{
    /** @param array<int,true> $codepoints */
    private function __construct(private array $codepoints, public bool $readable) {}

    public static function ofTrueType(string $bytes): self
    {
        $offset = self::tableOffset($bytes, 'cmap');
        if ($offset === null) {
            return new self([], false);
        }

        $subtable = self::bestSubtable($bytes, $offset);
        if ($subtable === null) {
            return new self([], false);
        }

        $format = self::uint16($bytes, $subtable);

        return match ($format) {
            4 => new self(self::readFormat4($bytes, $subtable), true),
            12 => new self(self::readFormat12($bytes, $subtable), true),
            default => new self([], false),
        };
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

    /**
     * Format 4, resolving each code point to a glyph id.
     *
     * A segment's range says which code points it *describes*, not which it can draw: `idDelta`,
     * `idRangeOffset` and `glyphIdArray` can still resolve a code point inside the range to glyph 0,
     * which means no glyph. Treating the range as coverage marked characters as available that the
     * font cannot draw, which is the false pass this whole class is meant to prevent.
     *
     * @return array<int,true>
     */
    private static function readFormat4(string $bytes, int $subtable): array
    {
        $segmentBytes = self::uint16($bytes, $subtable + 6);
        $segments = intdiv($segmentBytes, 2);
        $endBase = $subtable + 14;
        $startBase = $endBase + $segmentBytes + 2;
        $deltaBase = $startBase + $segmentBytes;
        $rangeBase = $deltaBase + $segmentBytes;

        $codepoints = [];
        for ($i = 0; $i < $segments; $i++) {
            $end = self::uint16($bytes, $endBase + $i * 2);
            $start = self::uint16($bytes, $startBase + $i * 2);
            if ($end === 0xFFFF || $start > $end) {
                continue;
            }
            $delta = self::uint16($bytes, $deltaBase + $i * 2);
            $rangeOffset = self::uint16($bytes, $rangeBase + $i * 2);

            for ($code = $start; $code <= $end; $code++) {
                if ($rangeOffset === 0) {
                    $glyph = ($code + $delta) & 0xFFFF;
                } else {
                    // The offset is measured in bytes from the idRangeOffset entry itself.
                    $at = $rangeBase + $i * 2 + $rangeOffset + ($code - $start) * 2;
                    if ($at + 2 > strlen($bytes)) {
                        continue;
                    }
                    $glyph = self::uint16($bytes, $at);
                    if ($glyph !== 0) {
                        $glyph = ($glyph + $delta) & 0xFFFF;
                    }
                }
                if ($glyph !== 0) {
                    $codepoints[$code] = true;
                }
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
