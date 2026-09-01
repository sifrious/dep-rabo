<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation;

/**
 * Every way a composition can be wrong, as a stable machine token.
 *
 * Codes are the contract; the prose message is for humans and may be reworded. A consumer
 * decides what to do by matching a code, never by parsing a sentence.
 */
enum IssueCode: string
{
    case BrandTokenUnknown = 'RABO_BRAND_TOKEN_UNKNOWN';
    case BrandFontUnknown = 'RABO_BRAND_FONT_UNKNOWN';
    case FontAssetMissing = 'RABO_FONT_ASSET_MISSING';
    case FontNotEmbeddable = 'RABO_FONT_NOT_EMBEDDABLE';
    case FontGlyphUnavailable = 'RABO_FONT_GLYPH_UNAVAILABLE';
    case FontAssetUnreadable = 'RABO_FONT_ASSET_UNREADABLE';
    case BrandDrift = 'RABO_BRAND_DRIFT';
    case ContrastInsufficient = 'RABO_CONTRAST_INSUFFICIENT';
    case AssetMissing = 'RABO_ASSET_MISSING';
    case AssetDigestMismatch = 'RABO_ASSET_DIGEST_MISMATCH';
    case AssetRightsMissing = 'RABO_ASSET_RIGHTS_MISSING';
    case TextOverflow = 'RABO_TEXT_OVERFLOW';
    case DimensionsInvalid = 'RABO_DIMENSIONS_INVALID';
    case NodeUnsupported = 'RABO_NODE_UNSUPPORTED';
    case TextAlternativeMissing = 'RABO_TEXT_ALTERNATIVE_MISSING';
    case ReadingOrderIncomplete = 'RABO_READING_ORDER_INCOMPLETE';
    case ColorOnlyEncoding = 'RABO_COLOR_ONLY_ENCODING';
    case LogoClearspaceViolated = 'RABO_LOGO_CLEARSPACE_VIOLATED';
    case MotionReducedVariantRequired = 'RABO_MOTION_REDUCED_VARIANT_REQUIRED';
    case MotionDurationInvalid = 'RABO_MOTION_DURATION_INVALID';
    case MotionCueOverlapUnresolved = 'RABO_MOTION_CUE_OVERLAP_UNRESOLVED';
    case MotionInformationOnlyTransient = 'RABO_MOTION_INFORMATION_ONLY_TRANSIENT';
    case RendererCapabilityUnsupported = 'RABO_RENDERER_CAPABILITY_UNSUPPORTED';
    case ReferenceRequiredMissing = 'RABO_REFERENCE_REQUIRED_MISSING';

    public function defaultSeverity(): Severity
    {
        return match ($this) {
            // A brand may legitimately rely on a system stack; the artifact still renders,
            // just not necessarily as the brand.
            self::BrandDrift, self::FontNotEmbeddable, self::FontGlyphUnavailable => Severity::Warning,
            default => Severity::Error,
        };
    }

    /** What a person should do about it. */
    public function remediation(): string
    {
        return match ($this) {
            self::BrandTokenUnknown => 'Reference a token the Brand Library declares, or add it to the brand.',
            self::BrandFontUnknown => 'Use a declared font family and a weight that family supports.',
            self::FontAssetMissing => 'Add the font bytes to the store, or drop the file declaration from the family.',
            self::FontNotEmbeddable => 'Ship a WOFF2 for this family, or accept that viewers without it see a fallback.',
            self::FontGlyphUnavailable => 'Use a font subset that covers the character, or rewrite the text to avoid it.',
            self::FontAssetUnreadable => 'Replace the font file; its character map could not be read, so nothing can be checked against it.',
            self::BrandDrift => 'Re-point the composition at a current Brand Library version.',
            self::ContrastInsufficient => 'Choose an ink role with more contrast against its fill, or change the fill.',
            self::AssetMissing => 'Add the asset bytes to the store, or reference an asset that exists.',
            self::AssetDigestMismatch => 'The stored bytes changed. Restore them, or re-address the asset.',
            self::AssetRightsMissing => 'Record the holder and licence before the asset is used in production.',
            self::TextOverflow => 'Shorten the text, widen the box, or allow more lines.',
            self::DimensionsInvalid => 'Give the canvas positive dimensions the renderer supports.',
            self::NodeUnsupported => 'Replace the node with a primitive Rabo defines.',
            self::TextAlternativeMissing => 'Describe the node for a reader who cannot see it.',
            self::ReadingOrderIncomplete => 'Add the node to the reading order, or mark it decorative.',
            self::ColorOnlyEncoding => 'Add a label so the meaning survives without colour.',
            self::LogoClearspaceViolated => 'Give the mark its declared clearspace and minimum width.',
            self::MotionReducedVariantRequired => 'Author a reduced-motion variant for this timeline.',
            self::MotionDurationInvalid => 'Give every cue a duration inside the timeline.',
            self::MotionCueOverlapUnresolved => 'Separate the cues, or state which one wins.',
            self::MotionInformationOnlyTransient => 'Keep essential content visible at the end, or put it in the reduced-motion variant.',
            self::RendererCapabilityUnsupported => 'Ask a renderer that declares this format and size.',
            self::ReferenceRequiredMissing => 'Attach the treatment and evidence this composition rests on.',
        };
    }
}
