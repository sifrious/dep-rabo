<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Asset;

/**
 * Where bytes live.
 *
 * The domain knows only that bytes can be stored and retrieved by their digest. Filesystem,
 * object storage, and content-delivery layouts are adapters; none of them may leak a path
 * shape into the contract.
 */
interface AssetStore
{
    public function has(ContentDigest $digest): bool;

    /** @throws MissingAsset */
    public function bytes(ContentDigest $digest): string;

    /** An opaque, adapter-specific locator, for diagnostics only. Never an identity. */
    public function locator(ContentDigest $digest): string;

    /** Stores bytes and returns the resulting content identity. */
    public function put(string $bytes): ContentDigest;
}
