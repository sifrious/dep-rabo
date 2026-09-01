# Rabo — working notes

Rabo owns what visual work looks like, how it is composed, how it moves, how it is validated,
and how a renderer is asked to draw it. It does not decide what to say, who to say it to,
whether it may be published, or where it goes.

## Boundaries that are not negotiable

**No framework, no ORM, no HTTP client, no provider SDK.** The only dependencies are `php` and
`sifrious/reference-contract`. If a class here needs Laravel or a network, the design is wrong.

**No Burdgen tokens.** Burdgen owns two token systems Rabo must never absorb: the semantic
interface palette (`--color-surface-*`, `--tone-*`) and the product identity palette
(`--product-*`, where `rabo` is itself a colour). A Brand Library is customer-facing brand
vocabulary. Reusing those names would collapse a boundary that ADR-002 exists to hold.

**No upstream or downstream state.** `ReferenceRole` records which package owns each role and
rejects anything else. Rabo holds references and resolves none of them. It never caches a
treatment's text, evidence bytes, or a review's verdict — those belong to packages that can
change them without telling Rabo.

**No renderer vocabulary in the domain.** `Brand`, `Composition`, and `Motion` contain no mention
of SVG, `resvg`, or `ffmpeg`. Those words appear only under `src/Renderer/`.

**Rendered artifacts never replace compositions.** An artifact is an output. Editing continues
against the scene.

## Things that will look like bugs and are not

**Text measurement is an estimate.** `Brand\TextFlow` multiplies a brand-declared advance ratio by
character count. There is no font engine and no font file. It is deliberately conservative — see
`docs/assumptions.md`. Validation and the painter share the one implementation, and they must
continue to: they were once separate, and a card passed validation then rendered with its last
word silently dropped.

**Maps are `stdClass` after `toArray()`.** Manifests emit maps as JSON objects so they stay
readable and avoid the `[]` versus `{}` ambiguity. That makes `toArray() === toArray()` always
false for equal documents. Use `canonical()` and `equals()`.

**A stack's `cross_sizing` is usually absent from `composition.json`.** `hug` is the default and is
not serialized, because emitting it would change `Composition::key()` for every composition that
never asked for the field — see D-017. Only `fill` appears.

**`fill` means "equal widths" or "equal heights" depending on the stack.** The cross axis is the one
the stack does not run along, so the same flag on a horizontal stack equalises heights and on a
vertical one equalises widths. That is deliberate: `columns` in the second composition wants both,
and gets them from one declaration because the variant flips its direction.

**`maxLines` is an allowance, not a requirement.** Text that fits on one line inside a two-line box
is correct. Height is measured against lines actually needed.

**The MP4 has no golden fixture and reports `deterministic: false`.** MP4 bytes vary across encoder
builds. The frame SVGs feeding it are reproducible and are what the tests assert on.

**Artifacts are ~140KB, not ~9KB.** They carry the brand's typefaces inlined as `@font-face` data
URIs. Without that they render in whatever the viewer has, which on a machine with none of the three
faces is a serif fallback — the state every artifact was in before MME-2194. `--no-embed-fonts`
gives the small file when you control the display environment.

**The font stack lists a family twice, sort of.** `'Space Grotesk', 'Space Grotesk Light', …` is not
a mistake. Browsers match the first against the inlined face; resvg cannot read `@font-face` at all
and matches the second against the TrueType file it was handed, which declares itself by that name
after its default axis instance.

**`≠` comes from a system font.** All three brand faces are latin subsets without U+2260. Validation
warns (`RABO_FONT_GLYPH_UNAVAILABLE`); the renderer deliberately does not pass `--skip-system-fonts`
so the character still draws. On a machine with no font covering it, the MP4 shows a box.

**`FrozenClock` is the default in the CLI.** Artifacts contain no timestamps, so renders are
byte-identical across runs. A timestamp anywhere in an artifact would destroy that.

**Floats are formatted with `%.2F`.** The uppercase form. Lowercase `%f` is locale-dependent and
would emit a comma decimal separator under some locales, silently producing different bytes on
different machines.

## Refusals, not warnings

A composition that fails validation does not render. Both SVG renderers validate first and return
`RenderOutcome::refused()` with a report and no artifact.

`RenderOutcome` has four states and they are not interchangeable. A refusal will never succeed on
retry. A transient failure probably will. An acknowledgement means the caller does not know
whether work happened, so resubmitting may duplicate it. Do not collapse these.

A missing `resvg` or `ffmpeg` is a refusal with `RABO_RENDERER_CAPABILITY_UNSUPPORTED`, not an
exception. The request is fine; this renderer cannot serve it.

Every `ValidationIssue` carries a machine code, the exact path at fault, and a remediation hint.
Consumers match on codes. Never make prose the only contract.

## Tests

`composer test`. Fixtures are the same files a reviewer reads: `tests/` loads
`fixtures/agent-completion-verified-completion` and `fixtures/failing/*` directly.

`tests/Docs/HumanVerificationTest.php` extracts the commands from `docs/human-verification.md`
itself and runs them, asserting the exit code each fenced block is annotated with, and failing if a
`php bin/rabo` line is documented without an annotation. That sentence used to name
`tests/Cli/CommandTest.php` and was simply untrue — no test opened any file under `docs/`, and the
gap hid `--no-embed-fonts`, documented in three places and read by nothing. When editing that page,
annotate new blocks (`expect-exit=N`, `no-errexit`, `requires=`, `requires-missing=`, `not-run=`).

Adding a validation code means adding a small bundle under `fixtures/failing/` that breaks that
rule and nothing else. `ValidationCoverageTest` asserts each bundle isolates exactly one code, and
scans the directory in both directions so neither the map nor the fixtures can fall behind.

Changing a renderer changes the committed artifacts under `expected/`. Regenerate them with
`bin/rabo render` and read the diff before committing it — that diff is the review.
