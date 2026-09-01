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

## Q-008 — Node sizes are fixed, so a variant cannot reflow to a new measure *(answered)*

Found by building a second composition. A `VariantSpec` can flip a stack's axis, but every node
carries a fixed `Size`, so nothing widens when the axis changes.

In the first fixture this was invisible: five narrow cards read the same side by side or stacked. In
the second, two 560-wide columns that sit edge to edge in landscape become a single 560-wide column
in a 1000-wide portrait, leaving 440 pixels of dead space beside it. The content is correct and the
layout is legal; it simply does not use the space it was given.

**Answered by D-017.** `CrossSizing::Fill` on a stack gives each of its container children the whole
inner cross extent. `root` and `columns` opt in, so the portrait cards now span their root: the two
changed lines in `expected/static-portrait.svg` are `width="560"` becoming `width="1000"`.

The hazard this entry named was real and was closed first, in its own commit, before the feature
existed. `TextOverflowRule` now measures the box layout resolved rather than the one the node
declares — a no-op at the time, since the two could not yet differ — and
`tests/Validation/TextMeasureAgreementTest.php` asserts the rule's width against the lines the
painter actually drew, so the two can no longer drift apart unnoticed.

`Fill` then avoids the question entirely for text: it applies only to children that declare no size,
which is every container and no leaf. No text box changes width, so nothing can rewrap. The
remaining edges are Q-011.

## Q-009 — `currentColor` cannot cross an embedded image boundary

The mono mark is authored with `fill="currentColor"` so it takes the ink colour of whatever it sits
in. Placed through an `ImageNode`, it becomes a separate document in a data URI and `currentColor`
resolves to that document's own default — black — rather than the brand's `text-strong`, which is a
warm near-black.

So a mono mark is always pure black, subtly off-brand. **It is now reported** —
`RABO_MARK_INK_NOT_INHERITED`, at warning severity, raised by `MarkTreatmentRule` when a mark's
bytes are an SVG using `currentColor`. The artifact still renders, so it warns rather than refuses;
the point is that the report says it instead of leaving it to be found in a rendered artifact.

The question itself stays open, because reporting is not fixing. Inlining the mark's markup into the
host document rather than referencing it as an image would let brand colour reach the mark, at the
cost of the renderer having to parse and namespace foreign SVG. Still not obviously worth it — but
no longer silent while it is not.

## Q-010 — Two compositions on one brand duplicate the brand

A bundle is self-contained by design: everything needed to validate and render lives under one path.
That is what makes it portable, and it is why the second composition ships its own copy of
`brand.json` and its own asset store — about 330 KB of it typefaces.

Self-contained and non-duplicating pull in opposite directions. Two compositions is fine; twenty is
6 MB of identical font bytes. Some indirection will be needed — a bundle naming a brand directory,
or a store that several bundles share — and it should be chosen deliberately rather than when the
repository gets uncomfortable.

## Q-011 — Two edges of cross-axis fill that were deliberately left alone

Recorded by D-017 so neither is rediscovered as a surprise.

**A leaf that fills.** `Fill` skips any node with a declared size, which is what keeps validation and
drawing measuring the same box. Letting a leaf fill would reopen D-005 directly, and
`test_a_declared_size_is_the_box_the_layout_resolves` currently forbids it — deliberately, so that
anyone who wants it has to answer the question rather than discover it in a rendered artifact.

**A scene root that fills its canvas.** `Layout::of()` sizes the root from `measure()`, never from
the available space, so a stack-level flag can never make the root wider than its content. That is
also what keeps `fixtures/failing/text-overflow` failing: its root measures 200, so there is no
track to fill. A root that filled its canvas would give that fixture 552px and is the one change
that could weaken it. If it is ever wanted, that fixture needs checking first.
