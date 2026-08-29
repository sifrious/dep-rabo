# Assumptions register

## A-001 — Text measurement is an estimate, not shaping

Confirmed by construction. `Brand\TextFlow` multiplies a brand-declared average advance ratio by
character count. It does not do kerning, ligatures, bidi, or script shaping, and it does not open
a font file.

The estimate is used identically by validation and by the SVG renderer, so the two always agree
with each other. They may both be wrong about a real browser, in one of two directions: a string
of narrow characters may be reported as overflowing when it would have fitted, and a string of
wide ones may pass and then overflow slightly. Ratios in the fixture brand are set conservatively
for that reason.

If a composition ever needs typographic precision — justified copy, a tight lockup — the honest
move is a renderer that measures, not a better guess here.

## A-002 — Clearspace is checked against laid-out geometry

Confirmed by `MarkTreatmentRule`. Distance is measured edge to edge between a mark's box and any
other node sharing a horizontal or vertical band with it. Nodes that overlap the mark on both axes
are skipped, because "distance" is not defined for them.

This catches a mark crowded by its neighbours in a stack, which is the common case. It does not
model a mark overlapping a busy background image, because Rabo has no notion of visual density.

## A-003 — Reading order is authored, not derived from position

Confirmed by `Scene::$readingOrder`. The order a sighted reader takes from a diagram's layout and
the order a screen reader should announce are not always the same, and only a person knows which
is intended. Validation checks that every essential text node appears; it does not check that the
order is sensible, which it cannot.

## A-004 — Animated SVG plays as authored in current browsers

Assumed, not verified across a browser matrix. The output uses CSS keyframes with `both` fill and
a `prefers-reduced-motion` block. It renders correctly in the rasterizer used here and in current
Chromium, Firefox and WebKit to the best of our knowledge, but no automated cross-browser check
runs in this repository.

The MP4 path exists partly because it does not depend on this assumption.

## A-005 — The fixture brand is real, licensed material

Confirmed. `fixtures/agent-completion-verified-completion` uses the `burg` identity from
`sifrious/official-burd-design`, which is MIT licensed. The two marks are that package's
`burg-mark.svg` and its single-colour derivative, stored under their real digests. Rights travel
with the assets in `assets.json`.

Nothing about the domain model is specific to that brand; it is one manifest among possible ones.

## A-006 — The evidence cited in the composition is real and checkable

Confirmed at time of writing. The composition's evidence references point at the handoff standard
requiring per-criterion results and commit SHAs, and at six `[RABO-00x]` tickets that sat in
"In Review" with no attachments and no implementation. That second reference is a claim about
state that will change as those tickets are reconciled; it is a reference to an observation, not
an assertion that the observation is still current.
