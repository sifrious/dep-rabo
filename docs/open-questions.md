# Open questions

## Q-001 — Where does a brand's font file live?

The Brand Library names families and declares metrics for them, but references no font binaries.
A rendered SVG relies on the viewer having the family installed or falling back down the declared
stack. For a published artifact that is a real gap: a social card rendered on a machine without
Space Grotesk will not look like the brand.

Options are embedding subsetted WOFF2 as content-addressed assets and inlining them, or leaving
font delivery to whichever consumer publishes. Not answered here because the first proof is
inspected locally.

## Q-002 — Should a template constrain a composition, or only suggest one?

`Template` currently names a canvas and the roles a composition built from it is expected to fill,
and nothing enforces either. Whether departing from a template should be a warning, an error, or
silent is a brand-governance question that has not been asked yet.

## Q-003 — How should a composition depend on more than one brand?

A composition pins exactly one brand. Co-branded work, and work that has to render in both a light
and a dark brand, have no representation. Adding a second pin is easy; deciding which brand's
accessibility floor governs is not.

## Q-004 — What happens when evidence a composition cites is retracted?

Rabo holds an opaque reference. If Digory or Funes later tombstones or supersedes that object, the
composition still renders and still claims support it may no longer have. The reference contract
models tombstoned and superseded states, so the information is available; nothing in Rabo checks
for it, and it is not obvious that Rabo should be the thing that does.

## Q-005 — Should captions be a first-class track?

Cue captions currently produce a text equivalent for the whole piece. Timed captions for narrated
motion — start, end, speaker — would need a caption track rather than a per-cue string. Deferred
until a composition actually carries speech.

## Q-006 — How does anything but one laptop install this package?

`composer.json` reaches `sifrious/reference-contract` at `git@github.com-sifrious:…`, which is an
SSH alias in one developer's `~/.ssh/config` and resolves nowhere else. Both repositories are
private, so substituting an `https://` URL does not help either — it moves the failure from DNS to
authentication.

CI works around it with a `url.…insteadOf` rewrite gated on a `SIFRIOUS_PACKAGES_TOKEN` secret, and
fails with an explicit message until that secret exists. That unblocks this package; it does not
answer the question for the six repositories with the same line.

Tracked as MME-2193, with four options recorded there. Worth noting that this was invisible to
local verification — a clean `git clone` into a scratch directory passed, because it ran on the one
machine where the alias resolves.
