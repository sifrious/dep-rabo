<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Brand;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Brand\FontCoverage;

/**
 * A character map that lies about its own shape must be unreadable, not empty.
 *
 * The distinction matters because "covers nothing" and "could not be read" lead to opposite
 * conclusions: the first says every character is missing, the second says nothing was checked. An
 * `idRangeOffset` pointing past the subtable would otherwise take bytes from an unrelated table and
 * report them as a glyph id, marking a character covered that the font cannot draw.
 */
final class FontCoverageBoundsTest extends TestCase
{
    public function test_a_font_with_no_character_map_is_unreadable(): void
    {
        self::assertFalse(FontCoverage::ofTrueType('not a font at all')->readable);
    }

    public function test_a_well_formed_subtable_reports_its_coverage(): void
    {
        $coverage = FontCoverage::ofTrueType($this->fontWithRangeOffset(0));

        self::assertTrue($coverage->readable);
        self::assertTrue($coverage->covers(0x0041), 'A maps through idDelta to a real glyph.');
    }

    public function test_a_range_offset_pointing_past_the_subtable_is_unreadable(): void
    {
        // Far beyond the declared subtable length, into whatever bytes follow.
        $coverage = FontCoverage::ofTrueType($this->fontWithRangeOffset(0x7000));

        self::assertFalse($coverage->readable, 'Reading past the subtable must not be treated as coverage.');
        self::assertFalse($coverage->covers(0x0041));
    }

    public function test_a_glyph_id_of_zero_is_not_coverage(): void
    {
        // idDelta is added modulo 65536, so this is the delta that maps 'A' onto glyph 0 — the
        // notdef glyph, which is the absence of a glyph rather than a drawable one.
        $coverage = FontCoverage::ofTrueType($this->fontWithRangeOffset(0, delta: (0x10000 - 0x0041) & 0xFFFF));

        self::assertTrue($coverage->readable);
        self::assertFalse($coverage->covers(0x0041), 'Glyph 0 is the absence of a glyph.');
    }

    /**
     * A minimal TrueType font carrying one cmap format 4 subtable.
     *
     * Two segments, as the format requires: one real range and the mandatory 0xFFFF terminator.
     */
    private function fontWithRangeOffset(int $rangeOffset, int $delta = 1, int $start = 0x0041, int $end = 0x0041): string
    {
        $subtable = pack('n*', 4, 32, 0, 4, 4, 1, 0)   // format, length, language, segCountX2, search fields
            .pack('n*', $end, 0xFFFF)                   // endCode
            .pack('n', 0)                               // reservedPad
            .pack('n*', $start, 0xFFFF)                 // startCode
            .pack('n*', $delta, 1)                      // idDelta
            .pack('n*', $rangeOffset, 0);               // idRangeOffset

        $cmap = pack('n*', 0, 1).pack('nnN', 3, 1, 12).$subtable;
        $header = pack('N', 0x00010000).pack('n*', 1, 16, 0, 0);
        $record = 'cmap'.pack('N*', 0, 12 + 16, strlen($cmap));

        return $header.$record.$cmap;
    }
}
