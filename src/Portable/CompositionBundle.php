<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Portable;

use InvalidArgumentException;
use RuntimeException;
use Sifrious\Rabo\Asset\Asset;
use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Asset\Filesystem\FilesystemAssetStore;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Composition;
use Sifrious\Rabo\Motion\MotionComposition;

/**
 * A composition, its brand, its assets, and its motion, as a directory.
 *
 * This is what "portable" means concretely: everything needed to validate and render lives
 * under one path, with no database and no application configuration, so another process — or
 * another machine, or a consumer package like the Content Engine — can pick it up whole.
 *
 * The layout is:
 *
 *     brand.json         the Brand Library manifest
 *     composition.json   the editable composition and its variants
 *     motion.json        optional; a timeline over one of its scenes
 *     assets.json        optional; rights and derivation records
 *     assets/            content-addressed bytes, sha256/<aa>/<rest>
 */
final readonly class CompositionBundle
{
    /** @param array<string,Asset> $assetRecords */
    private function __construct(
        public string $path,
        public BrandLibrary $brand,
        public Composition $composition,
        public ?MotionComposition $motion,
        public AssetStore $assets,
        public array $assetRecords,
    ) {}

    public static function load(string $path): self
    {
        $path = rtrim($path, '/');
        if (! is_dir($path)) {
            throw new InvalidArgumentException("No composition bundle at {$path}.");
        }

        $brand = BrandLibrary::fromArray(self::json($path.'/brand.json'));
        $composition = Composition::fromArray(self::json($path.'/composition.json'));

        $motion = null;
        if (is_file($path.'/motion.json')) {
            $motion = MotionComposition::fromArray(self::json($path.'/motion.json'));
            if (! $motion->appliesTo($composition)) {
                throw new InvalidArgumentException("Motion '{$motion->id}' does not apply to composition '{$composition->id}'.");
            }
        }

        $records = [];
        if (is_file($path.'/assets.json')) {
            $set = self::json($path.'/assets.json');
            foreach ($set['assets'] ?? [] as $serialized) {
                if (! is_array($serialized)) {
                    throw new InvalidArgumentException('Serialized assets must be objects.');
                }
                $asset = Asset::fromArray($serialized);
                $records[(string) $asset->digest] = $asset;
            }
        }

        return new self($path, $brand, $composition, $motion, new FilesystemAssetStore($path.'/assets'), $records);
    }

    public function hasMotion(): bool
    {
        return $this->motion !== null;
    }

    public function motionOrFail(): MotionComposition
    {
        return $this->motion ?? throw new RuntimeException("Bundle at {$this->path} carries no motion.json.");
    }

    /** @return array<string,mixed> */
    private static function json(string $file): array
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new InvalidArgumentException('Missing '.basename($file).' in the composition bundle.');
        }
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException(basename($file).' must contain a JSON object.');
        }

        return $decoded;
    }
}
