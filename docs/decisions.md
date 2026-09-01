# Decision register

Decisions taken while implementing MME-1864 and its children. Each entry records the decision,
why, and what was rejected.

## D-001 — Colours are canonically sRGB hex

**Decision.** `ColorToken` requires a six-digit lowercase sRGB hex value. An optional `display`
string carries the authoring-space form (oklch, lab) for round-tripping to design tools, and no
Rabo code computes with it.

**Rationale.** Contrast validation must be exact and identical on every machine and in every
renderer. WCAG relative luminance is defined over sRGB; computing it from an oklch string means
shipping a colour-space conversion whose rounding becomes part of a pass/fail decision.

**Rejected.** oklch as the canonical form, matching the house CSS. It would have made the manifest
prettier and every contrast result slightly arguable.

## D-002 — SVG is a renderer, never the domain model

**Decision.** The composition model knows nothing about SVG. Three adapters implement `Renderer`;
none of their vocabulary appears in `Brand`, `Composition`, or `Motion`.

**Rationale.** The whole point of the composition model is that a headline can be edited without
touching a rendered file. If SVG structure leaked into the domain, "edit the scene" and "edit the
output" would slowly become the same operation.

**Rejected.** Generating SVG directly from a builder API. Faster to a first picture, and it would
have made the second output format a rewrite.

## D-003 — Motion is a timeline over the existing scene

**Decision.** A `MotionComposition` references a composition and a scene by identifier and adds
cues over nodes that scene already contains. It carries no geometry and no copy of its own.

**Rationale.** The still version and the moving version have to say the same thing. Two separate
documents drift the moment someone edits one headline.

**Rejected.** A scene-per-beat storyboard, which is how a video tool would model it, and which
would have made "change the headline" a five-place edit.

## D-004 — The reduced-motion alternative is derived, not authored

**Decision.** `ReducedMotionStrategy` is a required field; the alternative timeline is computed
from the source timeline.

**Rationale.** An accessible alternative that has to be remembered will eventually be forgotten,
and one authored separately will eventually disagree with the original. Deriving it makes both
failures unrepresentable.

**Rejected.** An optional `reduced_motion` composition reference. Honest about the work involved,
and it would have made the accessible path the one that rots.

## D-005 — Text wrapping lives beside the brand, not inside a renderer

**Decision.** `Brand\TextFlow` performs greedy word wrapping from the brand's declared advance
ratios. Both `TextOverflowRule` and `ScenePainter` use it.

**Rationale.** This was found the hard way. The rule originally divided box width by allowed
lines while the renderer broke on word boundaries; a card passed validation and rendered with its
last word silently dropped. Wrapping is one algorithm or it is two answers.

**Rejected.** Keeping a wrapper in the SVG renderer. It read more naturally there and was wrong.

## D-006 — Rabo has no font engine, and says so

**Decision.** Font families declare average advance-width ratios per weight. Overflow is estimated
from those, and `docs/assumptions.md` records that this is an estimate.

**Rationale.** A real shaping engine means a font-file dependency, per-platform variation, and a
much larger package, in exchange for precision that a validation gate does not need. A declared,
brand-owned estimate is deterministic and inspectable.

**Rejected.** Shipping a shaper, or measuring nothing at all. The first is disproportionate; the
second leaves overflow to be discovered in review.

## D-007 — Four render outcomes, not two

**Decision.** `RenderOutcome` distinguishes succeeded, refused, failed transiently, and
acknowledged, with a private constructor and named factories fixing which fields each may carry.

**Rationale.** A caller must treat them differently: a refusal will never succeed on retry, a
transient failure probably will, and an acknowledgement means the caller does not know whether
work happened, so resubmitting may duplicate it. Collapsing these into a boolean pushes that
judgement onto every consumer.

**Rejected.** `?RenderArtifact` with an exception for failure, which cannot express the difference
between "will never work" and "try again in thirty seconds".

## D-008 — An unsatisfiable brand pin blocks; ordinary drift warns

**Decision.** `RABO_BRAND_DRIFT` defaults to a warning, and `BrandCompatibilityRule` raises it at
error severity when the pinned brand cannot be satisfied.

**Rationale.** `RenderRequest` already refuses to construct against an unsatisfiable pin. Reporting
the same condition as a non-blocking warning would have validation disagree with rendering.

**Rejected.** Making every drift an error, which would leave no way to say "this is still fine, but
it is not the current brand".

## D-009 — MP4 is an adapter, and its non-determinism is declared

**Decision.** The `ffmpeg` adapter samples the timeline into still SVGs through the same frame
renderer the tests assert on, rasterizes each with `resvg`, and encodes the sequence. It reports
`deterministic: false` and records both tool versions in provenance. The MP4 is not a committed
artifact.

**Rationale.** MP4 bytes vary across encoder builds. Claiming reproducibility that cannot be
verified would undermine the one guarantee this package is trying to demonstrate. Keeping the
frames reproducible preserves the guarantee where it can actually hold.

**Rejected.** Committing an MP4 as a golden file, and claiming determinism the encoder does not
provide.

## D-010 — Missing binaries are a refusal, not a crash

**Decision.** `BinaryProbe` is an interface. When `resvg` or `ffmpeg` is absent the adapter
declares no capability and returns `RABO_RENDERER_CAPABILITY_UNSUPPORTED`.

**Rationale.** The request is well-formed; this renderer simply cannot serve it, and the caller
should ask a different one. Making it an interface also lets all four outcome states be tested
without installing or removing anything.

**Rejected.** Throwing on a missing binary, which turns a routing decision into an incident.

## D-011 — A variant may re-orient and re-align, and nothing else

**Decision.** `VariantSpec` carries a canvas, per-stack direction overrides, per-stack alignment
overrides, and an optional padding step. It cannot change text, styles, or sizes.

**Rationale.** A variant that could change copy would be a second composition wearing the first
one's name. Restricting it to layout is what guarantees the square and landscape outputs say the
same thing — which is asserted directly in `CompositionContractTest`.

**Rejected.** Arbitrary per-variant node overrides. More flexible, and it would have made "the
variants have drifted" a thing that can happen.

## D-012 — Equality is canonical JSON, not array identity

**Decision.** `BrandLibrary`, `Composition`, `Scene`, and `CompositionReferences` expose
`canonical()` and `equals()`; comparisons use those.

**Rationale.** Maps that may be empty are emitted as JSON objects via `(object)` casts, which keeps
the manifests readable and avoids the empty-map ambiguity between `[]` and `{}`. Two equal
documents therefore hold distinct `stdClass` instances and are never identical by `===`, so the
house `toArray() === toArray()` idiom silently reports inequality.

**Rejected.** Emitting maps as sorted key/value pair lists, which would restore `===` at the cost
of a manifest no one wants to read or hand-edit.

## D-013 — This package has CI, unlike its neutral siblings

**Decision.** `.github/workflows/ci.yml` runs `composer install`, `composer test`, and
`composer audit` on PHP 8.3 and 8.4 for every push to `main` and every pull request.

**Rationale.** No framework-neutral package in this organisation has CI — `dep-reference-contract`,
`dep-titan`, `dep-elwin` and `dep-logres` all ship none, and every `dep-*` repo that does have CI
pulls Laravel. This package is a deliberate exception for one specific reason: it commits golden
artifacts that the tests regenerate and compare byte for byte. A renderer change can silently
invalidate them for anyone who does not run the suite locally, and an artifact that no longer
matches its own recorded provenance is exactly the failure this package exists to argue against.

The workflow installs from the committed lock rather than updating, because reproducibility is the
property under test. It runs 8.3 because `composer.json` declares `^8.3`, and a declared floor that
is never exercised is a claim rather than a fact.

`resvg` and `ffmpeg` are deliberately absent from the runner. The suite covers that case with a
stubbed `BinaryProbe`, so CI also proves the MP4 adapter refuses deterministically rather than
crashing when its tools are missing.

**Rejected.** Matching the neutral-package convention and shipping no CI. Defensible for a package
of pure value objects; not for one whose correctness claim is "these committed bytes regenerate
exactly".

**What it caught immediately.** The first run failed on both legs, and not on anything the tests
cover: `composer.json` addresses its one dependency through an SSH alias that exists only in one
developer's `~/.ssh/config`. Local verification had passed, including a clean `git clone` into a
scratch directory — because that clone happened on the machine where the alias resolves. The check
that looked like proof of portability was measuring something else. See `docs/open-questions.md`
Q-006 and MME-2193.
## D-014 — Typefaces are content-addressed assets, in two formats

**Decision.** A `FontFamily` declares `FontFile` records. The WOFF2 is inlined into rendered SVG as
an `@font-face` data URI; the TrueType is written to disk and passed to the rasterizer by path. The
TrueType is stored as an `AssetDerivation` of the WOFF2, transform `woff2-decompress`. Each OFL
licence text is stored as its own asset and named from the font's `AssetRights.terms`.

**Rationale.** Before this, artifacts named `'Space Grotesk', …, sans-serif` and rendered in whatever
the viewer had. None of the three faces were installed on the machine that built them, so every
artifact this package had ever produced was a serif fallback and nothing said so. "Portable, validated
brand composition" was true only where the brand was already installed.

Two formats because no single one satisfies both consumers, which was established by experiment
rather than assumed: browsers honour `@font-face`; resvg reports `The @font-face rule is not
supported. Skipped.` and rejects WOFF2 with `malformed font`. A single artifact serves both because
`FontFamily::stack()` inserts the name the TrueType file declares for itself — "Space Grotesk Light",
after that variable font's default axis instance — directly after the family's own name.

Fonts are assets rather than a new concept because they *are* assets: bytes with an identity, rights,
and a derivation. The model already expressed all of it.

**Rejected.** Depending on `sifrious/official-burd-design`, which already ships these files and is the
one properly tagged package in the organisation — it requires `illuminate/view`, and taking it would
put Laravel in this package's dependency tree and break the boundary in
`docs/package-boundary-validation.md` row 1.

Also rejected: rasterizing with headless Chrome, which honours `@font-face` and would need no font
conversion at all, but would replace a twelve-second frame pipeline with several hundred browser
invocations.

## D-015 — Glyph coverage is checked, and a gap is a warning

**Decision.** `FontCoverage` parses a TrueType `cmap` directly. `FontAssetRule` reports
`RABO_FONT_GLYPH_UNAVAILABLE`, at warning severity, for every character a scene sets that its family
cannot draw.

**Rationale.** The canonical composition's headline is "Agent completion ≠ verified completion", and
none of the brand's three latin subsets has a glyph for U+2260. Without this check the artifact
passes validation, renders correctly on a machine with a system font covering `≠`, and renders a tofu
box everywhere else — the same shape of failure as D-005, where validation and drawing disagreed.

A warning rather than an error because the artifact does still render, and because a brand may
knowingly rely on a system stack. The point is that the report says so.

See D-016 for what happens when the `cmap` cannot be read at all.

## D-016 — An unreadable character map is its own error, not a hundred derived warnings

**Decision.** `FontCoverage` carries a `readable` flag separate from its codepoint count, and
`FontAssetRule` reports `RABO_FONT_ASSET_UNREADABLE` at error severity for a font whose `cmap`
could not be parsed. It does not report a `RABO_FONT_GLYPH_UNAVAILABLE` warning for every character
in the scene.

**Rationale.** "Parsed, and covers nothing" and "could not be parsed" are different facts that both
produce zero codepoints. Collapsing them let an unreadable file answer a coverage question it had
never actually read, which is a false pass of exactly the kind D-015 exists to prevent.

Error rather than warning because, unlike a genuine glyph gap, nothing about the outcome is
knowingly accepted: the check did not run. Reporting it once rather than per character is the
difference between a report that names a broken file and a report that buries it under a hundred
consequences of that file.

**Rejected.** Treating an unparseable font as covering nothing, which was the original behaviour and
which D-015's own text still described for some time after it had been replaced — long enough for an
automated reviewer to read the stale paragraph and file a defect against correct code.

## D-017 — A stack may fill its cross axis, and it lives on the stack

**Decision.** `CrossSizing` is a two-case enum — `Hug`, the default and the original behaviour, and
`Fill` — carried by `StackNode` alongside `direction`, `gap`, `align` and `distribute`. Under `Fill`,
`Layout::place()` gives each child the stack's whole inner cross extent, but only a child that
returns null from `declaredSize()`. `Layout::measure()` is unchanged.

**Rationale.** A variant could re-orient a stack but nothing widened, so the green-checks portrait
put two 560-wide columns in a 1000-wide root and left 440px beside content that was correct, legal,
and simply did not use the room it had — Q-008.

Only container children fill because their extent was always the stack's to compute:
`Node::declaredSize()` returns null "when its container computes it". An authored size is never
overridden. That is not a nicety — it is what keeps `TextOverflowRule` and `ScenePainter` measuring
the same box, which is D-005, and it means no text can rewrap as a result of this feature.

`measure()` stays bottom-up so a filled child never grows its parent. Filling consumes space already
allocated; it never asks for more, so the change cannot cascade upward and the scene root is still
sized by what it contains rather than by its canvas.

On `StackNode` rather than `VariantSpec` because a composition should be able to say "this card
fills its row" in its own right, not only through a derivation. **D-011 therefore stands unamended:**
the variant surface gains nothing, and per-stack overrides can be added on top of this primitive
later if a real composition needs them.

**Serialization.** `cross_sizing` is emitted only when it is not `Hug`. This departs from every other
`toArray()` here, which emit total key sets — and the general rule it establishes is that *a field
added after a contract version is frozen is emitted only when it departs from what the contract
meant before it existed*. `Composition::key()` is a content identity: always emitting would mint a
new key for every existing composition while the rendered bytes stayed identical, so provenance
would report a change that had not happened. The reader side already defaults every missing key, so
nothing had to change to accept it.

**Consequences worth knowing.** `Fill` supersedes `align` for the children it fills — a node
occupying the whole track has no alignment left to have — while leaving `align` in force for any
sibling that declares a size. And the cross axis follows `direction`: on a horizontal stack `Fill`
equalises heights, on a vertical one it equalises widths. The same flag on `columns` therefore means
"equal-height cards" in landscape and "equal-width cards" in portrait, which is what was wanted in
both.

**Rejected.** A fourth `Alignment` case. `Alignment` is used for both `align` and `distribute`, and
stretching is meaningless on the main axis, so it would have needed a runtime rejection; and a
filled child has no alignment, so the case would not have been a peer of the other three but a value
that disabled them.

Also rejected: a size mode on `Node`. It would put a placement decision on objects that place
nothing, and give a leaf both a declared size and a flag saying whether to honour it — two sources
for one fact.
