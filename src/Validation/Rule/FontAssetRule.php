<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Asset\CorruptAsset;
use Sifrious\Rabo\Asset\MissingAsset;
use Sifrious\Rabo\Brand\FontCoverage;
use Sifrious\Rabo\Brand\FontFamily;
use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * The typefaces a scene sets must actually travel with it.
 *
 * A family declaring a font file the store does not hold is an error: the brand is claiming
 * something untrue about itself. A family with no embeddable file is only a warning — the artifact
 * still renders, just in whatever the viewer happens to have, and some brands genuinely do rely on
 * a system stack.
 *
 * The warning is not hypothetical. Every artifact this package produced before font embedding
 * existed rendered in a serif fallback, on a machine where none of the three brand faces were
 * installed, and nothing said so.
 */
final readonly class FontAssetRule implements Rule
{
    public function name(): string
    {
        return 'font-asset';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $store = $context->assets;
        $issues = [];

        // Embedding was asked for and there is nothing to embed from. Without this the renderer
        // quietly produces an artifact with no font faces at all, which is exactly the
        // non-portable output the embedding exists to prevent.
        if ($context->target?->embedFonts === true && $store === null) {
            $issues[] = new ValidationIssue(
                IssueCode::FontAssetMissing,
                'brand.typography',
                'This render embeds fonts but was given no asset store, so no typeface can travel with the artifact.',
            );
        }

        // File presence and integrity are checked for every family the brand declares, not only the
        // ones this scene sets. A brand that lies about its own contents is wrong whichever scene
        // happens to be rendering.
        foreach ($context->brand->typography->families as $family) {
            foreach ($family->files as $file) {
                if ($store === null) {
                    continue;
                }
                if (! $store->has($file->digest)) {
                    $issues[] = new ValidationIssue(
                        IssueCode::FontAssetMissing,
                        'brand.typography.'.$family->name,
                        sprintf(
                            "Font family '%s' declares a %s file at %s, which the store does not hold.",
                            $family->name, $file->format->value, $file->digest,
                        ),
                    );

                    continue;
                }
                try {
                    $store->bytes($file->digest);
                } catch (CorruptAsset) {
                    $issues[] = new ValidationIssue(
                        IssueCode::AssetDigestMismatch,
                        'brand.typography.'.$family->name,
                        sprintf("The bytes stored for font family '%s' no longer hash to %s.", $family->name, $file->digest),
                    );
                } catch (MissingAsset) {
                    $issues[] = new ValidationIssue(
                        IssueCode::FontAssetMissing,
                        'brand.typography.'.$family->name,
                        sprintf("Font family '%s' declares %s, which could not be read.", $family->name, $file->digest),
                    );
                }
            }
        }

        $roles = [];
        foreach ($context->scene->nodes() as $node) {
            $role = $node->style()->typeRole;
            if ($role !== null) {
                $roles[$role] = $role;
            }
        }
        ksort($roles);

        foreach ($context->brand->typography->familiesForRoles(array_values($roles)) as $family) {
            $raster = $family->rasterFile();
            if ($store !== null && $raster !== null && $store->has($raster->digest)
                && ! FontCoverage::ofTrueType($store->bytes($raster->digest))->readable) {
                $issues[] = new ValidationIssue(
                    IssueCode::FontAssetUnreadable,
                    'brand.typography.'.$family->name,
                    sprintf(
                        "Font family '%s' ships a file whose character map could not be read, so nothing was checked against it.",
                        $family->name,
                    ),
                );
            }

            foreach ($this->uncoveredCharacters($context, $family) as $character => $where) {
                $issues[] = new ValidationIssue(
                    IssueCode::FontGlyphUnavailable,
                    $where,
                    sprintf(
                        "Font family '%s' has no glyph for '%s', so that character comes from whatever the viewer has installed, or from nothing at all.",
                        $family->name, $character,
                    ),
                );
            }

            if ($family->embeddableFile() === null) {
                $issues[] = new ValidationIssue(
                    IssueCode::FontNotEmbeddable,
                    'brand.typography.'.$family->name,
                    sprintf(
                        "Font family '%s' ships no embeddable file, so an artifact using it renders in whatever the viewer has installed.",
                        $family->name,
                    ),
                );
            }
        }

        return $issues;
    }

    /**
     * Characters the family cannot draw, mapped to the first node that uses them.
     *
     * @return array<string,string>
     */
    private function uncoveredCharacters(ValidationContext $context, FontFamily $family): array
    {
        $store = $context->assets;
        $file = $family->rasterFile();
        if ($store === null || $file === null || ! $store->has($file->digest)) {
            return [];
        }

        $coverage = FontCoverage::ofTrueType($store->bytes($file->digest));
        if (! $coverage->readable) {
            // Reported separately as RABO_FONT_ASSET_UNREADABLE. Claiming every character is
            // missing here would bury that one fact under a hundred derived ones.
            return [];
        }

        $uncovered = [];
        foreach ($context->scene->nodes() as $node) {
            if (! $node instanceof TextNode) {
                continue;
            }
            $role = $node->style()->typeRole;
            if ($role === null
                || ! $context->brand->typography->hasRole($role)
                || $context->brand->typography->role($role)->family !== $family->name) {
                continue;
            }
            foreach ($coverage->missingFrom($node->content) as $character) {
                $uncovered[$character] ??= (string) $node->id();
            }
        }

        return $uncovered;
    }
}
