# Glossary

These terms are the vault's normative Rabo vocabulary
(`vault/thoughts/Projects/Rabo/Glossary/Glossary.md`), with the class that implements each.
Rabo owns these terms for customer-facing production. Burdgen owns the separate
product-interface design-system vocabulary, and none of it appears here.

- **Brand Library** — the portable source of truth for a visual identity, its assets, templates,
  motion language, rules, provenance, and rights. `Brand\BrandLibrary`.
- **Brand manifest** — the versioned, machine-readable document form of a Brand Library.
  `brand.json`; `BrandLibrary::toArray()`.
- **Brand token** — a named visual or motion decision: a semantic colour, a type role, a spacing
  value, a duration, an easing curve. `Brand\ColorToken`, `Brand\TypeRole`, `Brand\NumericScale`,
  `Brand\MotionTokens`.
- **Asset** — content-addressed source material with identity, provenance, rights, and
  relationships to derived variants. `Asset\Asset`, addressed by `Asset\ContentDigest`.
- **Static template** — an editable composition for a non-temporal output. `Brand\TemplateKind::StaticTemplate`.
- **Scene template** — an editable composition with a timeline. `Brand\TemplateKind::SceneTemplate`.
- **Motion token** — a reusable duration, easing, or timing rule. `Brand\MotionTokens`.
- **Reduced-motion variant** — an intentionally authored accessible alternative that removes or
  minimises non-essential motion. `Motion\ReducedMotionStrategy`,
  `MotionComposition::reducedMotionTimeline()`.
- **Renderer** — a replaceable adapter converting a composition into an output artifact.
  `Render\Renderer`.
- **Render request** — a machine-readable request naming source scene, target format, dimensions,
  and accessibility requirements. `Render\RenderRequest`.
- **Variant** — a derived but traceable output for a channel, aspect ratio, theme, locale, or
  accessibility requirement. `Composition\VariantSpec`.
- **Brand drift** — a working or published asset using obsolete, unapproved, inaccessible, or
  improperly licensed brand material. `Validation\IssueCode::BrandDrift`.

Terms this package adds:

- **Composition** — the envelope holding a canonical scene, its variants, its brand pin, and its
  cross-package references. `Composition\Composition`.
- **Composition bundle** — a Brand Library, composition, motion, and content-addressed assets as
  one directory, portable between processes. `Portable\CompositionBundle`.
- **Cue** — one timed change to one node. `Motion\Cue`.
- **Render outcome** — succeeded, refused, failed transiently, or acknowledged. `Render\RenderOutcome`.
