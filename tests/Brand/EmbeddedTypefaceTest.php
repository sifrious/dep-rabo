<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Brand;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Brand\FontCoverage;
use Sifrious\Rabo\Brand\FontFormat;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Render\FrozenClock;
use Sifrious\Rabo\Render\RenderFormat;
use Sifrious\Rabo\Render\RenderRequest;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Renderer\Svg\SvgStaticRenderer;
use Sifrious\Rabo\Tests\Fixture;

final class EmbeddedTypefaceTest extends TestCase
{
    public function test_each_family_ships_a_file_for_both_consumers(): void
    {
        $brand = CompositionBundle::load(Fixture::path())->brand;

        foreach ($brand->typography->families as $family) {
            self::assertNotNull($family->embeddableFile(), "Family '{$family->name}' has nothing a browser can inline.");
            self::assertNotNull($family->rasterFile(), "Family '{$family->name}' has nothing a rasterizer can load.");
            self::assertSame(FontFormat::Woff2, $family->embeddableFile()->format);
            self::assertSame(FontFormat::TrueType, $family->rasterFile()->format);
        }
    }

    /**
     * The stack has to satisfy two engines that disagree about everything.
     *
     * A browser matches the first entry against the inlined `@font-face`. A rasterizer cannot read
     * `@font-face` at all and matches by the name the font file declares for itself — which for
     * this variable Space Grotesk is "Space Grotesk Light", after its default axis instance.
     */
    public function test_the_font_stack_names_what_the_file_calls_itself(): void
    {
        $brand = CompositionBundle::load(Fixture::path())->brand;

        $spaceGrotesk = $brand->typography->family('Space Grotesk');
        self::assertSame('Space Grotesk Light', $spaceGrotesk->rasterFile()->declaredFamily);
        self::assertStringContainsString("'Space Grotesk', 'Space Grotesk Light'", $spaceGrotesk->stack());

        // A file that names itself correctly adds nothing to the stack.
        $hanken = $brand->typography->family('Hanken Grotesk');
        self::assertNull($hanken->rasterFile()->declaredFamily);
        self::assertStringStartsWith("'Hanken Grotesk', system-ui", $hanken->stack());
    }

    public function test_a_rendered_artifact_carries_the_typefaces_it_sets(): void
    {
        $svg = (string) file_get_contents(Fixture::path('expected/static.svg'));

        foreach (['Space Grotesk', 'Hanken Grotesk', 'JetBrains Mono'] as $family) {
            self::assertStringContainsString("@font-face { font-family: '{$family}'", $svg);
        }
        self::assertStringContainsString('src: url(data:font/woff2;base64,', $svg);
        self::assertGreaterThan(100_000, strlen($svg), 'An artifact carrying three typefaces is not a nine-kilobyte file.');
    }

    public function test_embedding_can_be_declined_for_a_controlled_environment(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $renderer = new SvgStaticRenderer($bundle->assets, new FrozenClock());

        $bare = $renderer->render(new RenderRequest(
            $bundle->composition, $bundle->brand, 'source',
            new RenderTarget(RenderFormat::Svg, $bundle->composition->scene->canvas, null, false, embedFonts: false),
        ));

        self::assertTrue($bare->isSuccess());
        self::assertStringNotContainsString('@font-face', $bare->artifactOrFail()->bytes);
        self::assertLessThan(20_000, $bare->artifactOrFail()->byteLength());
        // The stack still names both, so a rasterizer with the files on disk is unaffected.
        self::assertStringContainsString('Space Grotesk Light', $bare->artifactOrFail()->bytes);
    }

    public function test_embedding_is_deterministic(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $renderer = new SvgStaticRenderer($bundle->assets, new FrozenClock());
        $request = new RenderRequest(
            $bundle->composition, $bundle->brand, 'source',
            new RenderTarget(RenderFormat::Svg, $bundle->composition->scene->canvas),
        );

        self::assertSame(
            $renderer->render($request)->artifactOrFail()->bytes,
            $renderer->render($request)->artifactOrFail()->bytes,
        );
    }

    public function test_font_coverage_reads_the_characters_a_face_can_actually_draw(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $file = $bundle->brand->typography->family('Space Grotesk')->rasterFile();
        $coverage = FontCoverage::ofTrueType($bundle->assets->bytes($file->digest));

        self::assertGreaterThan(200, $coverage->count());
        self::assertTrue($coverage->covers(0x41), 'A latin subset covers "A".');
        self::assertFalse($coverage->covers(0x2260), 'This latin subset has no "≠".');
        self::assertSame(['≠'], $coverage->missingFrom('Agent completion ≠ verified completion'));
        self::assertSame([], $coverage->missingFrom('Agent completion is not verified completion'));
    }

    public function test_unreadable_font_bytes_are_reported_as_covering_nothing(): void
    {
        // Failing towards a false warning is right; failing towards a false pass is not.
        self::assertSame(0, FontCoverage::ofTrueType('not a font at all')->count());
    }
}
