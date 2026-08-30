<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\Rabo\Asset\CorruptAsset;
use Sifrious\Rabo\Asset\MissingAsset;
use Sifrious\Rabo\Composition\Node\ImageNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * Every referenced asset must exist and still hash to its own address.
 *
 * Without a store this rule stays silent rather than guessing: an absent store means "not
 * checked here", which is different from "checked and fine", and the two must not be
 * confused.
 */
final readonly class AssetRule implements Rule
{
    public function name(): string
    {
        return 'asset';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $store = $context->assets;
        if ($store === null) {
            return [];
        }

        $issues = [];
        $required = [];
        foreach ($context->scene->nodes() as $node) {
            if ($node instanceof ImageNode) {
                $required[(string) $node->asset] = ['digest' => $node->asset, 'path' => (string) $node->id()];
            }
        }
        // Font files are referenced assets too, but FontAssetRule owns them: it can name the
        // family and the format, which is a far more useful thing to be told. One fact, one rule.
        $fonts = [];
        foreach ($context->brand->typography->families as $family) {
            foreach ($family->assets() as $digest) {
                $fonts[(string) $digest] = true;
            }
        }
        foreach ($context->brand->referencedAssets() as $digest) {
            if (isset($fonts[(string) $digest])) {
                continue;
            }
            $required[(string) $digest] ??= ['digest' => $digest, 'path' => 'brand.marks'];
        }
        ksort($required);

        foreach ($required as $entry) {
            /** @var ContentDigest $digest */
            $digest = $entry['digest'];
            $path = $entry['path'];

            if (! $store->has($digest)) {
                $issues[] = new ValidationIssue(IssueCode::AssetMissing, $path, "Asset {$digest} is referenced but the store does not hold it.");

                continue;
            }
            try {
                $store->bytes($digest);
            } catch (CorruptAsset) {
                $issues[] = new ValidationIssue(IssueCode::AssetDigestMismatch, $path, "The bytes stored for {$digest} no longer hash to that digest.");
            } catch (MissingAsset) {
                $issues[] = new ValidationIssue(IssueCode::AssetMissing, $path, "Asset {$digest} could not be read from the store.");
            }
        }

        return $issues;
    }
}
