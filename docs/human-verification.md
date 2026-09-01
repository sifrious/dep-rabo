# Human verification

Every command below is extracted from this file and run by `tests/Docs/HumanVerificationTest.php`,
which asserts the exit code each block is annotated with. A command documented here that stops
working fails the suite, and a `php bin/rabo` line added here without an annotation fails it too —
so this page cannot quietly drift away from the package. Run them from the repository root after
`composer install`.

The annotations (`expect-exit=0`, `requires=…`, `not-run=…`) sit in the code-fence info string and
do not render. Exit codes are what this page binds; what the output *means* is asserted by the
dedicated tests each section names.

## 1. The suite

```sh not-run=recursive
composer test
```

**Expect:** exit `0`, and `OK` with no failures. The exact test count is deliberately not quoted
here — it changes with every slice, and a number in prose that nothing checks is exactly the kind
of claim this page exists to stop making.

## 2. The canonical bundle validates

```sh expect-exit=0
php bin/rabo validate fixtures/agent-completion-verified-completion
```

**Expect:** exit `0`. Standard output is a `sifrious.rabo.validation-report` with
`"passed": true`, `"error_count": 0`, and an empty `issues` array. Standard error reads
`PASS agent-completion-verified-completion: no blocking issues.`

## 3. Rendering produces four artifacts and their provenance

```sh expect-exit=0
php bin/rabo render fixtures/agent-completion-verified-completion --format=svg
php bin/rabo render fixtures/agent-completion-verified-completion --format=svg --scene=square
php bin/rabo render fixtures/agent-completion-verified-completion --format=svg-animated
php bin/rabo render fixtures/agent-completion-verified-completion --format=svg-animated --reduced-motion
```

**Expect:** exit `0` four times, and in `build/`:

| File | What it is |
| --- | --- |
| `static.svg` | 1200×630 still |
| `static-square.svg` | 1080×1080, derived from the same source scene |
| `motion.svg` | fifteen seconds in five beats |
| `reduced-motion.svg` | the same piece with the motion removed |

Each is accompanied by `<name>.provenance.json`.

Open `build/motion.svg` in a browser. The five cards arrive in sequence over fifteen seconds;
the arrows arrive just before the cards they point at. Open `build/reduced-motion.svg`: the same
content, fully assembled, no animation.

## 4. The committed artifacts are exactly what those commands produce

```sh expect-exit=0
diff -r build fixtures/agent-completion-verified-completion/expected
```

**Expect:** no output, exit `0`. Delete `build/`, re-run step 3, and diff again — the bytes are
identical every time, because the renderers take an injected clock and write no timestamps.

## 5. Provenance traces an artifact back to its sources

```sh expect-exit=0
php bin/rabo inspect build/static.svg
```

**Expect:** exit `0` and `PASS build/static.svg: bytes match the recorded output digest.` The
JSON names the composition id, version and content key; the brand id, version and key; both asset
digests; the renderer and its version; the request digest; the output digest; and the Pulp,
Digory and Funes references the composition rests on.

Now tamper with it:

```sh expect-exit=1
echo '<!-- edited -->' >> build/static.svg
php bin/rabo inspect build/static.svg
```

**Expect:** exit `1` and `FAIL ... the bytes on disk do not match the digest its provenance
records.` Step 6 re-renders it, which restores it.

## 6. The artifact displays in the brand's own typefaces

```sh expect-exit=0
php bin/rabo render fixtures/agent-completion-verified-completion --format=svg
grep -c '@font-face' build/static.svg          # 3
```

**Expect:** three inlined faces, and a file around 140 KB rather than 9 KB. Open it in a browser: the
headline is Space Grotesk, the body Hanken Grotesk, `"Done"` JetBrains Mono. **None of those three
faces needs to be installed** — that is the point. Before this existed, every artifact rendered in a
serif fallback on a machine with none of them, and nothing said so.

```sh expect-exit=0
php bin/rabo render fixtures/agent-completion-verified-completion --format=svg --no-embed-fonts --out=build/bare
```

**Expect:** exit `0`, no `@font-face`, around 9 KB — for anyone who controls the display
environment. `tests/Cli/CommandTest.php` asserts both the face count and the size, because this
flag was once documented in three places and read by nothing: it silently embedded the fonts
anyway, and this page promised a small file that the package never produced.

The validation report carries one honest warning:

```
RABO_FONT_GLYPH_UNAVAILABLE  headline
  Font family 'Space Grotesk' has no glyph for '≠' …
```

All three faces are latin subsets without U+2260, so that one character comes from a system font. The
report says so rather than letting it be discovered at publication.

## 6b. A second composition on the same brand

```sh expect-exit=0
php bin/rabo validate fixtures/green-checks-that-verified-nothing
php bin/rabo render  fixtures/green-checks-that-verified-nothing --format=svg --out=build/second
php bin/rabo render  fixtures/green-checks-that-verified-nothing --format=svg --scene=portrait --out=build/second
diff -r build/second fixtures/green-checks-that-verified-nothing/expected
```

**Expect:** exit `0` throughout, zero issues, and an empty diff.

This bundle exists to test whether the primitives generalise. It differs from the first in every
structural way available: stacks nested four deep rather than two, ellipses, no connectors at all,
the mark's mono variant, a 4:5 portrait derivation, and **no motion** — a bundle with no
`motion.json` is a complete bundle, not a broken one.

It also carries only two typefaces rather than three, because it sets nothing in mono:

```sh expect-exit=0
grep -c '@font-face' build/second/static.svg     # 2
```

## 7. Every validation dimension fails the way it should

```sh expect-exit=0 no-errexit
for d in fixtures/failing/*/; do
  php bin/rabo validate "$d" >/dev/null 2>&1
  exit_code=$?
  echo "$(basename "$d") exit=$exit_code"
done
```

Capture `$?` into a variable before the `echo`. Writing `echo "$(basename "$d") exit=$?"` reports
the exit code of `basename`, which is always `0`, and the loop then cheerfully claims every fixture
passed. Avoid the name `status`, which is read-only in zsh.

**Expect:** `exit=1` for every bundle. Each isolates exactly one code:

| Bundle | Code |
| --- | --- |
| `unknown-brand-token` | `RABO_BRAND_TOKEN_UNKNOWN` |
| `insufficient-contrast` | `RABO_CONTRAST_INSUFFICIENT` |
| `text-overflow` | `RABO_TEXT_OVERFLOW` |
| `missing-asset` | `RABO_ASSET_MISSING` |
| `missing-text-alternative` | `RABO_TEXT_ALTERNATIVE_MISSING` |
| `incomplete-reading-order` | `RABO_READING_ORDER_INCOMPLETE` |
| `missing-evidence-reference` | `RABO_REFERENCE_REQUIRED_MISSING` |
| `content-exceeds-canvas` | `RABO_DIMENSIONS_INVALID` |
| `brand-drift` | `RABO_BRAND_DRIFT` |
| `motion-cue-past-end` | `RABO_MOTION_DURATION_INVALID` |
| `motion-cue-overlap` | `RABO_MOTION_CUE_OVERLAP_UNRESOLVED` |
| `motion-essential-dismissed` | `RABO_MOTION_INFORMATION_ONLY_TRANSIENT` |
| `missing-font-asset` | `RABO_FONT_ASSET_MISSING` |
| `unreadable-font-asset` | `RABO_FONT_ASSET_UNREADABLE` |

`HumanVerificationTest` reads this table and fails if it does not name every directory under
`fixtures/failing/`, so the list cannot fall behind the fixtures again — it said "thirteen" while
fourteen existed, and two other documents said "twelve".

Check one in detail:

```sh expect-exit=1
php bin/rabo validate fixtures/failing/unknown-brand-token
```

**Expect:** exit `1`; the report's single issue is
`{"code": "RABO_BRAND_TOKEN_UNKNOWN", "severity": "error", "path": "headline", ...}` — it names
the exact node — with a remediation hint. Standard error repeats the code and path.

## 8. A failed validation prevents an artifact

```sh expect-exit=1
rm -rf build/refused
php bin/rabo render fixtures/failing/insufficient-contrast --format=svg --out=build/refused
```

**Expect:** exit `1`, `RABO_CONTRAST_INSUFFICIENT` on standard error, and **no `build/refused`
directory at all** — the refusal happens before anything is written, which
`tests/Cli/CommandTest.php` asserts directly. The refusal is deterministic: the same request will
never succeed until the composition changes.

## 9. Optional — the MP4 adapter

Without `resvg` and `ffmpeg` on `PATH`:

```sh expect-exit=1 requires-missing=resvg,ffmpeg
php bin/rabo render fixtures/agent-completion-verified-completion --format=mp4
```

**Expect:** exit `1`, status `refused`, code `RABO_RENDERER_CAPABILITY_UNSUPPORTED`, naming which
binary is missing. This is the correct answer, not a failure of the package: the request is fine,
this renderer cannot serve it. CI runs on a machine with neither tool for exactly this reason.

With them installed:

```sh not-run=environment
brew install ffmpeg resvg
```

```sh expect-exit=0 requires=resvg,ffmpeg
php bin/rabo render fixtures/agent-completion-verified-completion --format=mp4
ffprobe -v error -show_entries format=duration:stream=width,height,r_frame_rate \
  -of default=noprint_wrappers=1 build/motion.mp4
```

**Expect:** exit `0`; roughly 100 KB at `1200×630`, `24/1`, `duration=15.000000`. The brand's
TrueType files are written from the store and handed to the rasterizer, so the video is set in the
brand's own faces too — `provenance.renderer.tool_versions.embedded_fonts` records how many. The MP4 is
deliberately **not** a committed artifact — MP4 bytes vary across encoder builds, so its
provenance records `deterministic: false` along with the `resvg` and `ffmpeg` versions used. The
frame SVGs feeding it are reproducible and are what the tests assert on.
