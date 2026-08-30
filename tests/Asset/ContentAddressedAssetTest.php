<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Asset;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Asset\Asset;
use Sifrious\Rabo\Asset\AssetDerivation;
use Sifrious\Rabo\Asset\AssetRights;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Asset\CorruptAsset;
use Sifrious\Rabo\Asset\Filesystem\FilesystemAssetStore;
use Sifrious\Rabo\Asset\MissingAsset;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Tests\Fixture;

final class ContentAddressedAssetTest extends TestCase
{
    public function test_identical_bytes_resolve_to_the_same_identity(): void
    {
        $bytes = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        self::assertTrue(ContentDigest::ofBytes($bytes)->equals(ContentDigest::ofBytes($bytes)));
    }

    public function test_changed_bytes_resolve_to_a_different_identity(): void
    {
        self::assertFalse(ContentDigest::ofBytes('a')->equals(ContentDigest::ofBytes('b')));
    }

    public function test_a_source_and_its_derived_variant_keep_distinct_identities(): void
    {
        $derived = $this->assetLabelled('burg mark, single colour');
        $source = $this->assetLabelled('burg mark');

        self::assertFalse($source->isDerived());
        self::assertTrue($derived->isDerived());
        self::assertFalse($derived->digest->equals($source->digest), 'A derived variant is not the same asset.');
        self::assertTrue($derived->derivation->source->equals($source->digest), 'A derived variant names what it came from.');
        self::assertSame('single-colour', $derived->derivation->transform);
    }

    public function test_an_asset_cannot_be_derived_from_itself(): void
    {
        $digest = ContentDigest::ofBytes('x');

        $this->expectException(InvalidArgumentException::class);

        new Asset($digest, 'image/svg+xml', 1, new AssetRights('sifrious', 'MIT'), null, new AssetDerivation($digest, 'copy'));
    }

    public function test_the_store_addresses_the_fixture_assets_by_content(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));
        $store = new FilesystemAssetStore(Fixture::path('assets'));

        foreach ($brand->referencedAssets() as $digest) {
            self::assertTrue($store->has($digest), "The brand references {$digest}, which the store does not hold.");
            self::assertTrue($digest->matches($store->bytes($digest)), 'Stored bytes must hash back to their own address.');
        }
    }

    public function test_reading_an_absent_asset_fails_explicitly(): void
    {
        $store = new FilesystemAssetStore(Fixture::path('assets'));

        $this->expectException(MissingAsset::class);

        $store->bytes(ContentDigest::ofBytes('nothing stored under this digest'));
    }

    public function test_bytes_that_no_longer_match_their_address_are_reported_as_corruption(): void
    {
        $root = sys_get_temp_dir().'/rabo-store-'.bin2hex(random_bytes(6));
        $store = new FilesystemAssetStore($root);
        $digest = $store->put('original');
        file_put_contents($store->locator($digest), 'tampered');

        try {
            $this->expectException(CorruptAsset::class);
            $store->bytes($digest);
        } finally {
            @unlink($store->locator($digest));
        }
    }

    public function test_rights_and_provenance_survive_export_and_reimport(): void
    {
        $original = $this->assetLabelled('burg mark, single colour');
        $restored = Asset::fromArray(json_decode(json_encode($original, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));

        self::assertSame('MIT', $restored->rights->license);
        self::assertSame('sifrious', $restored->rights->holder);
        self::assertSame($original->rights->terms, $restored->rights->terms);
        self::assertSame($original->toArray(), $restored->toArray());
    }

    public function test_font_licences_travel_with_the_font_bytes(): void
    {
        $font = $this->assetLabelled('Space Grotesk (woff2)');
        $licence = $this->assetLabelled('Space Grotesk licence');

        self::assertSame('OFL-1.1', $font->rights->license);
        self::assertTrue($font->rights->attributionRequired);
        self::assertStringContainsString(
            (string) $licence->digest,
            (string) $font->rights->terms,
            'The font must name the licence asset that covers it, so the two cannot drift apart.',
        );
    }

    public function test_the_truetype_font_is_recorded_as_derived_from_the_woff2(): void
    {
        $woff2 = $this->assetLabelled('Space Grotesk (woff2)');
        $truetype = $this->assetLabelled('Space Grotesk (truetype)');

        self::assertTrue($truetype->isDerived());
        self::assertSame('woff2-decompress', $truetype->derivation->transform);
        self::assertTrue($truetype->derivation->source->equals($woff2->digest));
        self::assertFalse($truetype->digest->equals($woff2->digest));
    }

    public function test_a_path_is_a_locator_and_never_an_identity(): void
    {
        $store = new FilesystemAssetStore(Fixture::path('assets'));
        $digest = ContentDigest::ofBytes('anything');

        $expected = '/sha256/'.substr($digest->hex, 0, 2).'/'.substr($digest->hex, 2);

        self::assertStringEndsWith($expected, $store->locator($digest), 'The locator is the digest, fanned out.');
        self::assertStringNotContainsString('.svg', $store->locator($digest), 'Locators are derived from content, not from a filename.');
    }

    private function assetLabelled(string $label): Asset
    {
        foreach (Fixture::json('assets.json')['assets'] as $serialized) {
            if (($serialized['label'] ?? null) === $label) {
                return Asset::fromArray($serialized);
            }
        }

        self::fail("The fixture holds no asset labelled '{$label}'.");
    }
}
