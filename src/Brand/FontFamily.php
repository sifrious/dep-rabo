<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Asset\ContentDigest;

/**
 * A declared family, its fallback stack, and its declared advance-width ratios.
 *
 * `advanceRatios` maps a weight onto the average glyph advance as a fraction of the font
 * size. Rabo has no font engine and does not shape text; overflow validation multiplies
 * this brand-declared ratio by the character count. It is an estimate the BRAND owns, and
 * is deliberately conservative rather than accurate — see docs/assumptions.md.
 */
final readonly class FontFamily implements JsonSerializable
{
    /** @var list<string> */
    public array $fallbacks;

    /** @var list<int> */
    public array $weights;

    /** @var array<int,float> */
    public array $advanceRatios;

    /** @var list<FontFile> */
    public array $files;

    /**
     * @param  list<string>  $fallbacks
     * @param  list<int>  $weights
     * @param  array<int,float>  $advanceRatios
     * @param  list<FontFile>  $files
     */
    public function __construct(
        public string $name,
        array $fallbacks,
        array $weights,
        array $advanceRatios,
        array $files = [],
    ) {
        if (! self::isSafeFamilyName($name)) {
            throw new InvalidArgumentException('Font family names must be plain, non-empty typeface names.');
        }
        foreach ($fallbacks as $fallback) {
            if (! is_string($fallback) || ! self::isSafeFamilyName($fallback)) {
                throw new InvalidArgumentException("Font family '{$name}' has an invalid fallback entry.");
            }
        }
        if ($weights === []) {
            throw new InvalidArgumentException("Font family '{$name}' must declare at least one weight.");
        }
        sort($weights);
        foreach ($weights as $weight) {
            if (! is_int($weight) || $weight < 1 || $weight > 1000) {
                throw new InvalidArgumentException("Font family '{$name}' declares an out-of-range weight.");
            }
            if (! isset($advanceRatios[$weight])) {
                throw new InvalidArgumentException("Font family '{$name}' declares weight {$weight} without an advance ratio.");
            }
        }
        foreach ($advanceRatios as $weight => $ratio) {
            if (! is_int($weight) || ! is_float($ratio) || $ratio <= 0.0 || $ratio > 2.0) {
                throw new InvalidArgumentException("Font family '{$name}' has an invalid advance ratio.");
            }
        }
        ksort($advanceRatios);

        $seenFormats = [];
        foreach ($files as $file) {
            if (! $file instanceof FontFile) {
                throw new InvalidArgumentException("Font family '{$name}' accepts FontFile values only.");
            }
            if (isset($seenFormats[$file->format->value])) {
                throw new InvalidArgumentException("Font family '{$name}' declares two {$file->format->value} files.");
            }
            $seenFormats[$file->format->value] = true;
        }

        $this->fallbacks = array_values($fallbacks);
        $this->weights = array_values($weights);
        $this->advanceRatios = $advanceRatios;
        $this->files = array_values($files);
    }

    public function file(FontFormat $format): ?FontFile
    {
        foreach ($this->files as $file) {
            if ($file->format === $format) {
                return $file;
            }
        }

        return null;
    }

    /** The file a document can inline as a data URI, if the family ships one. */
    public function embeddableFile(): ?FontFile
    {
        foreach ($this->files as $file) {
            if ($file->format->isEmbeddable()) {
                return $file;
            }
        }

        return null;
    }

    /** The file a rasterizer can load from disk, if the family ships one. */
    public function rasterFile(): ?FontFile
    {
        return $this->file(FontFormat::TrueType);
    }

    /**
     * A typeface name that is safe to write into CSS and markup.
     *
     * Family names reach a rendered artifact's `<style>` block, so a name carrying a quote, an angle
     * bracket, a brace or a semicolon could close the rule or the element and inject markup into a
     * document a viewer opens. Real typeface names need none of those characters.
     */
    public static function isSafeFamilyName(string $name): bool
    {
        return trim($name) === $name
            && $name !== ''
            // A leading hyphen is legitimate: `-apple-system` is a real fallback entry.
            && preg_match('/^-?[\p{L}\p{N}][\p{L}\p{N} ._+-]*$/u', $name) === 1;
    }

    /** Every digest this family depends on — font files and the licences that must ship with them. */
    /** @return list<ContentDigest> */
    public function assets(): array
    {
        $digests = [];
        foreach ($this->files as $file) {
            $digests[] = $file->digest;
            if ($file->licence !== null) {
                $digests[] = $file->licence;
            }
        }

        return $digests;
    }

    public function supportsWeight(int $weight): bool
    {
        return in_array($weight, $this->weights, true);
    }

    public function advanceRatio(int $weight): float
    {
        return $this->advanceRatios[$weight]
            ?? throw new UnknownBrandToken("Font family '{$this->name}' declares no weight {$weight}.");
    }

    /**
     * The full CSS font stack, family first.
     *
     * Any family name a font file declares for itself is inserted straight after the family's own
     * name. That is what lets one artifact serve both consumers: a browser matches the first entry
     * against the inlined `@font-face`, while a rasterizer that cannot read `@font-face` at all
     * matches the next entry against a font file handed to it on the command line.
     */
    public function stack(): string
    {
        $declared = [];
        foreach ($this->files as $file) {
            if ($file->declaredFamily !== null && $file->declaredFamily !== $this->name) {
                $declared[$file->declaredFamily] = $file->declaredFamily;
            }
        }

        return implode(', ', array_map(
            static fn (string $family): string => str_contains($family, ' ') ? "'{$family}'" : $family,
            [$this->name, ...array_values($declared), ...$this->fallbacks],
        ));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $ratios = [];
        foreach ($this->advanceRatios as $weight => $ratio) {
            $ratios[(string) $weight] = $ratio;
        }

        return [
            'name' => $this->name,
            'fallbacks' => $this->fallbacks,
            'weights' => $this->weights,
            'advance_ratios' => (object) $ratios,
            'files' => array_map(static fn (FontFile $f): array => $f->toArray(), $this->files),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        $name = $serialized['name'] ?? null;
        $fallbacks = $serialized['fallbacks'] ?? [];
        $weights = $serialized['weights'] ?? null;
        $ratios = $serialized['advance_ratios'] ?? null;
        $files = $serialized['files'] ?? [];
        if (! is_string($name) || ! is_array($fallbacks) || ! is_array($weights) || ! is_array($ratios) || ! is_array($files)) {
            throw new InvalidArgumentException('Serialized font families require name, fallbacks, weights, advance ratios, and files.');
        }
        $decodedRatios = [];
        foreach ($ratios as $weight => $ratio) {
            $decodedRatios[(int) $weight] = (float) $ratio;
        }

        return new self(
            $name,
            array_values($fallbacks),
            array_map(intval(...), array_values($weights)),
            $decodedRatios,
            array_map(static function (mixed $file): FontFile {
                if (! is_array($file)) {
                    throw new InvalidArgumentException('Serialized font files must be objects.');
                }

                return FontFile::fromArray($file);
            }, array_values($files)),
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
