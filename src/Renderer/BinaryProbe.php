<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer;

/**
 * Whether an external tool is available, and which version.
 *
 * An interface rather than a bare `which` call so the four render outcomes can be exercised
 * in tests without installing or uninstalling anything on the machine running them.
 *
 * It sits above the adapters because more than one of them shells out: the MP4 encoder and the
 * PNG rasterizer both ask this the same question, and both refuse the same way when the answer
 * is no.
 */
interface BinaryProbe
{
    public function path(string $binary): ?string;

    public function version(string $binary): ?string;
}
