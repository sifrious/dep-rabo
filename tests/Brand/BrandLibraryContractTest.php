<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Brand;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Brand\BrandVersion;
use Sifrious\Rabo\Brand\ColorSystem;
use Sifrious\Rabo\Brand\ColorToken;
use Sifrious\Rabo\Brand\FontFamily;
use Sifrious\Rabo\Brand\TypeRole;
use Sifrious\Rabo\Brand\TypographySystem;
use Sifrious\Rabo\Brand\UnknownBrandToken;
use Sifrious\Rabo\Tests\Fixture;

final class BrandLibraryContractTest extends TestCase
{
    public function test_the_canonical_manifest_parses_without_an_application(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));

        self::assertSame('burg', $brand->id);
        self::assertSame('1.0.0', (string) $brand->version);
        self::assertSame('#ef9f27', $brand->colors->resolveRole('accent')->hex);
        self::assertSame(50, $brand->typography->role('headline')->sizePx);
        self::assertSame(3000, $brand->motion->durationMs('beat'));
        self::assertSame(24.0, $brand->spacing->step('lg'));
        self::assertNotSame([], $brand->referencedAssets());
    }

    public function test_serialization_round_trips_without_losing_meaning(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));
        $restored = BrandLibrary::fromArray(json_decode($brand->canonical(), true, flags: JSON_THROW_ON_ERROR));

        self::assertTrue($restored->equals($brand));
        self::assertSame($brand->key(), $restored->key());
    }

    public function test_a_colour_role_pointing_at_an_undeclared_token_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ColorSystem([new ColorToken('ink', '#000000')], ['accent' => 'nonexistent']);
    }

    public function test_resolving_an_undeclared_token_raises_an_unknown_brand_token(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));

        $this->expectException(UnknownBrandToken::class);

        $brand->colors->resolveRole('does-not-exist');
    }

    public function test_a_type_role_may_not_request_a_weight_its_family_does_not_declare(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TypographySystem(
            [new FontFamily('Space Grotesk', [], [400], [400 => 0.5])],
            [new TypeRole('headline', 'Space Grotesk', 900, 40, 1.1)],
        );
    }

    public function test_brand_compatibility_is_explicit_rather_than_assumed(): void
    {
        $pinned = BrandVersion::parse('1.2.0');

        self::assertTrue(BrandVersion::parse('1.2.0')->satisfies($pinned));
        self::assertTrue(BrandVersion::parse('1.3.1')->satisfies($pinned));
        self::assertFalse(BrandVersion::parse('1.1.9')->satisfies($pinned), 'An older minor cannot satisfy a newer pin.');
        self::assertFalse(BrandVersion::parse('2.0.0')->satisfies($pinned), 'A new major is never compatible.');
    }

    public function test_an_unsupported_contract_version_is_refused_rather_than_guessed(): void
    {
        $serialized = Fixture::json('brand.json');
        $serialized['contract_version'] = 99;

        $this->expectException(InvalidArgumentException::class);

        BrandLibrary::fromArray($serialized);
    }

    public function test_declared_advance_ratios_drive_the_overflow_estimate(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));

        // 15 characters at weight 700, ratio 0.545, 50px, tracking -0.02em over 14 gaps.
        $expected = 15 * 0.545 * 50 + 14 * -0.02 * 50;

        self::assertEqualsWithDelta($expected, $brand->typography->estimateWidthPx('headline', 'Agent completio'), 0.0001);
    }
}
