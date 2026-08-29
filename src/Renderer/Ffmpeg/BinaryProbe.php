<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Ffmpeg;

/**
 * Whether an external tool is available, and which version.
 *
 * An interface rather than a bare `which` call so the four render outcomes can be exercised
 * in tests without installing or uninstalling anything on the machine running them.
 */
interface BinaryProbe
{
    public function path(string $binary): ?string;

    public function version(string $binary): ?string;
}
