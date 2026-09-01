<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Resvg;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Scene;
use Sifrious\Rabo\Renderer\BinaryProbe;
use Sifrious\Rabo\Renderer\Svg\ScenePainter;
use Sifrious\Rabo\Renderer\SystemBinaryProbe;

/**
 * Turning an SVG into pixels with `resvg`, and handing it the brand's typefaces.
 *
 * Shared by the still PNG renderer and the MP4 encoder so the two cannot disagree about how a
 * scene is rasterized. They once would have: the MP4 path grew the font handling because a video
 * was silently rendering in a system fallback while the SVG rendered in the brand, and a second
 * copy of that logic is a second chance to get it wrong.
 *
 * `resvg` cannot read `@font-face` at all and rejects WOFF2 as malformed, so the brand's TrueType
 * files are written to disk and passed by path. Everything about that is here rather than in
 * either renderer.
 */
final readonly class Rasterizer
{
    public const BINARY = 'resvg';

    public function __construct(
        private ?AssetStore $assets = null,
        private BinaryProbe $probe = new SystemBinaryProbe(),
    ) {}

    public function available(): bool
    {
        return $this->probe->path(self::BINARY) !== null;
    }

    public function version(): ?string
    {
        return $this->probe->version(self::BINARY);
    }

    /**
     * Families this scene sets that ship no file a rasterizer can load.
     *
     * A WOFF2-only family passes validation — it is embeddable, so the SVG is fine — but resvg
     * rejects WOFF2, so the output would quietly fall back to whatever the machine has installed.
     *
     * @return list<string>
     */
    public function familiesWithoutRasterFile(BrandLibrary $brand, Scene $scene): array
    {
        if ($this->assets === null) {
            return [];
        }

        $missing = [];
        foreach ((new ScenePainter($brand, $this->assets))->familiesUsedBy($scene) as $family) {
            $file = $family->rasterFile();
            if ($file === null || ! $this->assets->has($file->digest)) {
                $missing[] = $family->name;
            }
        }

        return $missing;
    }

    /**
     * Writes the brand's rasterizable font files into a directory and returns the arguments
     * that load them.
     *
     * @return list<string>
     */
    public function fontArguments(BrandLibrary $brand, Scene $scene, string $directory): array
    {
        if ($this->assets === null) {
            return [];
        }

        $arguments = [];
        foreach ((new ScenePainter($brand, $this->assets))->familiesUsedBy($scene) as $family) {
            $file = $family->rasterFile();
            if ($file === null || ! $this->assets->has($file->digest)) {
                continue;
            }
            $path = $directory.'/font-'.substr($file->digest->hex, 0, 16).'.ttf';
            file_put_contents($path, $this->assets->bytes($file->digest));
            $arguments[] = '--use-font-file';
            $arguments[] = $path;
        }

        return $arguments;
    }

    /**
     * @param  list<string>  $fontArguments
     * @return array{status:int,output:string}
     */
    public function rasterize(string $svgPath, string $pngPath, int $width, int $height, array $fontArguments): array
    {
        return $this->run([
            (string) $this->probe->path(self::BINARY),
            '--width', (string) $width,
            '--height', (string) $height,
            ...$fontArguments,
            $svgPath, $pngPath,
        ]);
    }

    /**
     * @param  list<string>  $command
     * @return array{status:int,output:string}
     */
    private function run(array $command): array
    {
        $escaped = implode(' ', array_map(escapeshellarg(...), $command));
        $output = [];
        $status = 0;
        exec($escaped.' 2>&1', $output, $status);

        return ['status' => $status, 'output' => implode("\n", array_slice($output, -5))];
    }
}
