<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Ffmpeg;

final class SystemBinaryProbe implements BinaryProbe
{
    /** @var array<string,string|null> */
    private array $paths = [];

    /** @var array<string,string|null> */
    private array $versions = [];

    public function path(string $binary): ?string
    {
        return $this->paths[$binary] ??= $this->locate($binary);
    }

    public function version(string $binary): ?string
    {
        if (array_key_exists($binary, $this->versions)) {
            return $this->versions[$binary];
        }
        $path = $this->path($binary);
        if ($path === null) {
            return $this->versions[$binary] = null;
        }
        // ffmpeg answers to -version, resvg to --version. Ask both rather than special-casing names.
        foreach (['--version', '-version'] as $flag) {
            $output = [];
            $status = 0;
            exec(escapeshellarg($path).' '.$flag.' 2>&1', $output, $status);
            if ($status === 0 && $output !== []) {
                return $this->versions[$binary] = trim($output[0]);
            }
        }

        return $this->versions[$binary] = null;
    }

    private function locate(string $binary): ?string
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $binary) !== 1) {
            return null;
        }
        $output = [];
        $status = 0;
        exec('command -v '.escapeshellarg($binary).' 2>/dev/null', $output, $status);

        return $status === 0 && $output !== [] ? trim($output[0]) : null;
    }
}
