# Rabo package boundary validation

Validated for MME-1864 on 2026-08-29.

| Criterion | Package evidence | Result |
| --- | --- | --- |
| Rabo depends on no framework, ORM, HTTP client, or provider SDK | `composer.json` requires `php ^8.3` and `sifrious/reference-contract` only. `grep -ril "illuminate\|laravel\|guzzle\|symfony" src/` returns nothing. | Passed |
| Burdgen product-interface design tokens are not absorbed | No `--color-surface-*`, `--tone-*`, or `--product-*` name appears in `src/` or in any fixture. The fixture brand is `burg` from `sifrious/official-burd-design`, a customer-facing identity, not Burdgen's theme model. | Passed |
| Cross-package references use the shared contract | `Reference\CompositionReferences` holds `Sifrious\ReferenceContract\CrossPackageReference` values and serializes them unchanged; asserted in `tests/Reference/CrossPackageReferenceTest.php`. | Passed |
| Rabo does not own upstream or downstream state | `ReferenceRole` records an owning package per role and rejects a reference owned by anything else, including Rabo itself. No resolution, caching, or copying of referenced objects exists anywhere in `src/`. | Passed |
| Renderer implementations stay behind the boundary | `Render\Renderer` names no format or tool. `Brand`, `Composition`, and `Motion` contain no reference to SVG, `resvg`, or `ffmpeg`; those appear only under `src/Renderer/`. | Passed |
| Rendered artifacts do not replace editable scenes | `RenderArtifact` carries bytes and a digest and is never an input to anything. Rendering does not mutate a `Composition`; `test_a_variant_is_derived_without_replacing_the_source_scene` asserts the source is untouched. | Passed |
| Validation depends on no hosted vendor | `CompositionValidator` and every `Rule` take only a composition, a brand, an optional asset store, and an optional capability. No clock, no network, no model. Every failing fixture under `fixtures/failing/` runs offline, and `ValidationCoverageTest` scans that directory rather than trusting a number written here. | Passed |
| A failed validation prevents an artifact | Both SVG renderers validate before painting and return `RenderOutcome::refused()` with no artifact; `test_a_render_that_would_be_invalid_writes_no_artifact` asserts nothing reaches disk. | Passed |
| Storage implementation stays outside the domain contract | `Asset\AssetStore` names only digests and bytes. `FilesystemAssetStore` is the sole adapter and its path layout appears nowhere else. | Passed |
| The package is usable without a database or application config | `Portable\CompositionBundle` loads everything from a directory; the whole vertical slice validates and renders from checked-in files. | Passed |
| Optional external tools remain optional | Removing `resvg` or `ffmpeg` from `PATH` changes nothing but the MP4 adapter's declared capability; `composer test` passes either way, asserted with a stubbed `BinaryProbe`. | Passed |

This validation uses package fixtures and the local filesystem. It proves the reusable boundary,
not the behaviour of a particular consumer. Content Engine integration is verified separately in
that repository.
