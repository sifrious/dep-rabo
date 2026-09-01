# Open questions

## Q-001 — Where does a brand's font file live? *(answered)*

Answered by MME-2194: in the content-addressed asset store, like any other asset.

A family declares its files. The WOFF2 is inlined into rendered SVG as an `@font-face` data URI,
which browsers honour. The TrueType — stored as an `AssetDerivation` of the WOFF2, transform
`woff2-decompress` — is written to disk and handed to the rasterizer by path, because resvg ignores
`@font-face` entirely and rejects WOFF2 as malformed. One artifact serves both, because the font
stack names the family the TrueType file declares for itself directly after the family's own name.

Licences travel as assets too: each OFL text is stored under its own digest and named from the
font's `AssetRights.terms`, so the licence cannot drift from the bytes it covers.


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

## Q-006 — How does anything but one laptop install this package? — answered

`composer.json` reached `sifrious/reference-contract` at `git@github.com-sifrious:…`, an SSH alias
in one developer's `~/.ssh/config` that resolved on that machine and nowhere else. CI failed on both
legs at its first run, at DNS rather than at authentication, with an error pointing at the wrong
thing.

**Answered.** The repository is public, so the dependency is now reached at
`https://github.com/sifrious/dep-reference-contract.git` and needs no credential at all. Verified in
a sandbox matching a runner — no SSH, no credential helper, no prompts — and then by CI on PHP 8.3
and 8.4.

Two things worth keeping from it. Making the repository public was necessary but not sufficient: the
alias had to be replaced too, because it fails at name resolution whether or not a credential is
needed. And this was invisible to local verification — a clean `git clone` into a scratch directory
passed, because it ran on the one machine where the alias resolves. The check that looked like proof
of portability was measuring something else.

Five other repositories still carry the same line. That is MME-2193, not Rabo's question.

## Q-007 — Should the brand ship a font subset that covers its own headline?

All three faces are latin subsets of about 230 glyphs, and none contains U+2260. The canonical
composition's headline turns on exactly that character, so `≠` is drawn by whatever the viewer has
installed, and by nothing at all on a machine with no such font.

Validation reports this as `RABO_FONT_GLYPH_UNAVAILABLE` rather than hiding it, and the headline
text is fixed by the brief, so the options are to ship a wider subset for the display face or to
accept a system fallback for one character. Widening the subset means re-vendoring from Fontsource
and re-deriving the TrueType, which is a small but real decision about what the brand carries.
