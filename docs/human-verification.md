# Human verification

Every command below is also run by the test suite (`tests/Cli/CommandTest.php`), so this page
and the package cannot drift apart. Run them from the repository root after `composer install`.

## 1. The suite

```sh
composer test
```

**Expect:** exit `0`, `OK (87 tests, 354 assertions)`.

## 2. The canonical bundle validates

```sh
php bin/rabo validate fixtures/agent-completion-verified-completion
```

**Expect:** exit `0`. Standard output is a `sifrious.rabo.validation-report` with
`"passed": true`, `"error_count": 0`, and an empty `issues` array. Standard error reads
`PASS agent-completion-verified-completion: no blocking issues.`

## 3. Rendering produces four artifacts and their provenance

```sh
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

```sh
diff -r build fixtures/agent-completion-verified-completion/expected
```

**Expect:** no output, exit `0`. Delete `build/`, re-run step 3, and diff again — the bytes are
identical every time, because the renderers take an injected clock and write no timestamps.

## 5. Provenance traces an artifact back to its sources

```sh
php bin/rabo inspect build/static.svg
```

**Expect:** exit `0` and `PASS build/static.svg: bytes match the recorded output digest.` The
JSON names the composition id, version and content key; the brand id, version and key; both asset
digests; the renderer and its version; the request digest; the output digest; and the Pulp,
Digory and Funes references the composition rests on.

Now tamper with it:

```sh
echo '<!-- edited -->' >> build/static.svg
php bin/rabo inspect build/static.svg
```

**Expect:** exit `1` and `FAIL ... the bytes on disk do not match the digest its provenance
records.` Re-render to restore.

## 6. Every validation dimension fails the way it should

```sh
for d in fixtures/failing/*/; do
  php bin/rabo validate "$d" >/dev/null 2>&1
  exit_code=$?
  echo "$(basename "$d") exit=$exit_code"
done
```

Capture `$?` into a variable before the `echo`. Writing `echo "$(basename "$d") exit=$?"` reports
the exit code of `basename`, which is always `0`, and the loop then cheerfully claims every fixture
passed. Avoid the name `status`, which is read-only in zsh.

**Expect:** exit `1` for all twelve. Each bundle isolates exactly one code:

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

Check one in detail:

```sh
php bin/rabo validate fixtures/failing/unknown-brand-token
```

**Expect:** exit `1`; the report's single issue is
`{"code": "RABO_BRAND_TOKEN_UNKNOWN", "severity": "error", "path": "headline", ...}` — it names
the exact node — with a remediation hint. Standard error repeats the code and path.

## 7. A failed validation prevents an artifact

```sh
rm -rf /tmp/rabo-refused
php bin/rabo render fixtures/failing/insufficient-contrast --format=svg --out=/tmp/rabo-refused
ls /tmp/rabo-refused
```

**Expect:** exit `1`, `RABO_CONTRAST_INSUFFICIENT` on standard error, and **no file written**.
The refusal is deterministic: the same request will never succeed until the composition changes.

## 8. Optional — the MP4 adapter

Without `resvg` and `ffmpeg` on `PATH`:

```sh
php bin/rabo render fixtures/agent-completion-verified-completion --format=mp4
```

**Expect:** exit `1`, status `refused`, code `RABO_RENDERER_CAPABILITY_UNSUPPORTED`, naming which
binary is missing. This is the correct answer, not a failure of the package: the request is fine,
this renderer cannot serve it.

With them installed:

```sh
brew install ffmpeg resvg
php bin/rabo render fixtures/agent-completion-verified-completion --format=mp4
ffprobe -v error -show_entries format=duration:stream=width,height,r_frame_rate \
  -of default=noprint_wrappers=1 build/motion.mp4
```

**Expect:** exit `0`; roughly 100 KB at `1200×630`, `24/1`, `duration=15.000000`. The MP4 is
deliberately **not** a committed artifact — MP4 bytes vary across encoder builds, so its
provenance records `deterministic: false` along with the `resvg` and `ffmpeg` versions used. The
frame SVGs feeding it are reproducible and are what the tests assert on.
