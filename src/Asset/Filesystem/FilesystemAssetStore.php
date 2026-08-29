<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Asset\Filesystem;

use InvalidArgumentException;
use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Asset\CorruptAsset;
use Sifrious\Rabo\Asset\MissingAsset;

/**
 * A content-addressed store on disk.
 *
 * Layout is `<root>/sha256/<first two hex characters>/<remaining hex>`. The fan-out exists
 * only to keep directories small; nothing reads meaning from it. Reading verifies the
 * digest, so bytes that were altered underneath the store are reported as corruption
 * rather than served.
 */
final readonly class FilesystemAssetStore implements AssetStore
{
    public function __construct(private string $root)
    {
        if (trim($root) === '') {
            throw new InvalidArgumentException('A filesystem asset store requires a root directory.');
        }
    }

    public function has(ContentDigest $digest): bool
    {
        return is_file($this->path($digest));
    }

    public function bytes(ContentDigest $digest): string
    {
        $path = $this->path($digest);
        if (! is_file($path)) {
            throw new MissingAsset("No stored bytes for {$digest}.");
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new MissingAsset("Stored bytes for {$digest} could not be read.");
        }
        if (! $digest->matches($bytes)) {
            throw new CorruptAsset("Stored bytes at {$path} do not hash to {$digest}.");
        }

        return $bytes;
    }

    public function locator(ContentDigest $digest): string
    {
        return $this->path($digest);
    }

    public function put(string $bytes): ContentDigest
    {
        $digest = ContentDigest::ofBytes($bytes);
        $path = $this->path($digest);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new CorruptAsset("Could not create asset directory {$directory}.");
        }
        if (! is_file($path) && file_put_contents($path, $bytes) === false) {
            throw new CorruptAsset("Could not write asset bytes to {$path}.");
        }

        return $digest;
    }

    private function path(ContentDigest $digest): string
    {
        return $this->root
            .DIRECTORY_SEPARATOR.ContentDigest::ALGORITHM
            .DIRECTORY_SEPARATOR.substr($digest->hex, 0, 2)
            .DIRECTORY_SEPARATOR.substr($digest->hex, 2);
    }
}
