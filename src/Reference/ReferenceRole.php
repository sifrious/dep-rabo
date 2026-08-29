<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Reference;

/**
 * What a referenced object is to this composition.
 *
 * Each role declares which package owns the object and whether a composition may exist
 * without it. Rabo holds the reference and nothing else: it does not cache the treatment's
 * text, the evidence's bytes, or the review's verdict, because those belong to packages
 * that can change them without telling Rabo.
 */
enum ReferenceRole: string
{
    /** Pulp: why this composition exists at all. */
    case Treatment = 'treatment';

    /** Pulp: the campaign or plan the treatment sits in. */
    case ContentPlan = 'content_plan';

    /** Pulp: the beat-by-beat intent a motion composition realises. */
    case Storyboard = 'storyboard';

    /** Digory, Funes, or Aleph: what makes the claim on screen supportable. */
    case Evidence = 'evidence';

    /** Orual: the editorial decision taken about this artifact. */
    case Review = 'review';

    /** Trout: the channel delivery this artifact was prepared for. */
    case Delivery = 'delivery';

    /** Trout: where it actually went. */
    case Publication = 'publication';

    /** The packages permitted to own a reference in this role. */
    /** @return list<string> */
    public function owners(): array
    {
        return match ($this) {
            self::Treatment, self::ContentPlan, self::Storyboard => ['sifrious/pulp'],
            self::Evidence => ['sifrious/digory', 'sifrious/funes', 'sifrious/aleph'],
            self::Review => ['sifrious/orual'],
            self::Delivery, self::Publication => ['sifrious/trout'],
        };
    }

    /** Roles a composition may not be considered complete without. */
    public function isRequired(): bool
    {
        return $this === self::Treatment || $this === self::Evidence;
    }

    public function allowsMany(): bool
    {
        return $this === self::Evidence;
    }
}
