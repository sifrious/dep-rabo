<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Docs;

use PHPUnit\Framework\TestCase;

/**
 * `docs/human-verification.md`, run as a test.
 *
 * That page told a reviewer to type seventeen commands and claimed the suite ran all of them. It
 * ran none: no test in this package opened any file under `docs/`. The gap was not theoretical —
 * it hid `--no-embed-fonts`, which was documented in three places, read by nothing, and promised a
 * file under 20 KB while producing one of 139 KB.
 *
 * So the commands are extracted from the page itself rather than copied into a list here. A copied
 * list is a second thing to keep in sync, which is the failure this is fixing.
 *
 * Each `sh` fence carries its expectation in the info string:
 *
 *   expect-exit=N            run it; the block must exit N
 *   no-errexit               do not stop the block at its first failing command
 *   requires=a,b             skip unless every named binary is on PATH
 *   requires-missing=a,b     skip unless every named binary is absent
 *   not-run=<reason>         documented deliberately, never executed
 *
 * Exit codes are all this binds. What the output *means* stays asserted by the tests each section
 * of the page names, which can say it far more precisely than a shell exit code can.
 */
final class HumanVerificationTest extends TestCase
{
    private const DOCUMENT = 'docs/human-verification.md';

    private static ?string $workspace = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$workspace !== null) {
            exec('rm -rf '.escapeshellarg(self::$workspace));
            self::$workspace = null;
        }
    }

    public function test_every_documented_command_still_does_what_the_page_says(): void
    {
        $blocks = self::blocks();
        self::assertNotEmpty($blocks, 'The page has no annotated shell blocks, so nothing is bound.');

        $ran = 0;
        foreach ($blocks as $block) {
            if ($block['not-run'] !== null) {
                continue;
            }
            if (! self::available($block['requires'], true) || ! self::available($block['requires-missing'], false)) {
                continue;
            }

            $expected = $block['expect-exit'];
            self::assertNotNull(
                $expected,
                "The block at line {$block['line']} is neither annotated with expect-exit nor marked not-run.",
            );

            $script = ($block['no-errexit'] ? '' : "set -e\n").$block['code'];
            $actual = self::shell($script, $output);
            $ran++;

            self::assertSame($expected, $actual, sprintf(
                "%s line %d expects exit %d, got %d.\n--- commands ---\n%s\n--- output ---\n%s",
                self::DOCUMENT, $block['line'], $expected, $actual, $block['code'], $output,
            ));
        }

        self::assertGreaterThanOrEqual(9, $ran, 'Far fewer blocks ran than the page documents.');
    }

    /**
     * The assertion that stops the page drifting again.
     *
     * A command can only be documented here by being annotated, and being annotated is what makes
     * it run. Adding a `php bin/rabo` line without one fails this test rather than silently
     * joining the set of claims nothing checks.
     */
    public function test_no_documented_rabo_command_escapes_the_annotated_blocks(): void
    {
        $covered = [];
        foreach (self::blocks() as $block) {
            foreach (self::raboLines($block['code']) as $line) {
                $covered[$line] = true;
            }
        }

        $orphans = [];
        foreach (self::raboLines(self::document()) as $line) {
            if (! isset($covered[$line])) {
                $orphans[] = $line;
            }
        }

        self::assertSame([], $orphans, sprintf(
            "These `php bin/rabo` lines in %s sit outside any annotated sh block, so nothing runs them:\n  %s",
            self::DOCUMENT,
            implode("\n  ", $orphans),
        ));
    }

    public function test_the_page_names_every_failing_fixture(): void
    {
        $document = self::document();
        $directories = glob(self::root().'/fixtures/failing/*', GLOB_ONLYDIR) ?: [];
        self::assertNotEmpty($directories);

        foreach ($directories as $directory) {
            $bundle = basename($directory);
            self::assertStringContainsString(
                "| `{$bundle}` |",
                $document,
                "fixtures/failing/{$bundle} exists but the table in ".self::DOCUMENT.' does not list it.',
            );
        }
    }

    /**
     * Every `php bin/rabo …` command line in a chunk of text, normalised for comparison.
     *
     * A line must *start* with the command to count, which is what a runnable line looks
     * like. The page also mentions the command in prose, and a sentence is not something
     * to execute.
     *
     * @return list<string>
     */
    private static function raboLines(string $text): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, 'php bin/rabo')) {
                $lines[] = (string) preg_replace('/\s+/', ' ', $trimmed);
            }
        }

        return $lines;
    }

    /**
     * Annotated `sh` fences, in document order.
     *
     * @return list<array{line:int,code:string,expect-exit:?int,no-errexit:bool,not-run:?string,requires:list<string>,requires-missing:list<string>}>
     */
    private static function blocks(): array
    {
        $lines = explode("\n", self::document());
        $blocks = [];
        $open = null;

        foreach ($lines as $index => $line) {
            if ($open === null) {
                if (preg_match('/^```sh\b(.*)$/', $line, $match) === 1) {
                    $open = ['line' => $index + 1, 'info' => trim($match[1]), 'code' => []];
                }

                continue;
            }
            if (trim($line) === '```') {
                $info = $open['info'];
                preg_match('/\bexpect-exit=(\d+)/', $info, $exit);
                preg_match('/\bnot-run=([\w-]+)/', $info, $notRun);

                $blocks[] = [
                    'line' => $open['line'],
                    'code' => implode("\n", $open['code']),
                    'expect-exit' => isset($exit[1]) ? (int) $exit[1] : null,
                    'no-errexit' => str_contains($info, 'no-errexit'),
                    'not-run' => $notRun[1] ?? null,
                    'requires' => self::binaries($info, 'requires'),
                    'requires-missing' => self::binaries($info, 'requires-missing'),
                ];
                $open = null;

                continue;
            }
            $open['code'][] = $line;
        }

        return $blocks;
    }

    /** @return list<string> */
    private static function binaries(string $info, string $key): array
    {
        // `requires-missing=` also matches a naive search for `requires=`, so anchor the name.
        if (preg_match('/(?<![\w-])'.preg_quote($key, '/').'=([\w,.-]+)/', $info, $match) !== 1) {
            return [];
        }

        return array_values(array_filter(explode(',', $match[1])));
    }

    /** @param list<string> $binaries */
    private static function available(array $binaries, bool $wanted): bool
    {
        foreach ($binaries as $binary) {
            $found = self::shell('command -v '.escapeshellarg($binary), $ignored) === 0;
            if ($found !== $wanted) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runs a block in a scratch directory that looks enough like the repository root.
     *
     * The page's commands write to `build/`, tamper with an artifact, and delete directories. They
     * do that here rather than in the working tree, while `fixtures/` and `bin/` are the real ones
     * so the commands under test are genuinely the documented ones.
     */
    private static function shell(string $script, ?string &$output): int
    {
        $workspace = self::$workspace ??= self::workspace();
        $lines = [];
        $status = 0;
        exec(
            // The newline matters: a block whose last line ends in a `# comment` would
            // otherwise swallow the closing brace.
            'cd '.escapeshellarg($workspace).' && { '.$script."\n} 2>&1",
            $lines,
            $status,
        );
        $output = implode("\n", $lines);

        return $status;
    }

    private static function workspace(): string
    {
        $workspace = sys_get_temp_dir().'/rabo-docs-'.bin2hex(random_bytes(6));
        mkdir($workspace, 0o775, true);
        foreach (['bin', 'src', 'vendor', 'fixtures', 'composer.json'] as $entry) {
            symlink(self::root().'/'.$entry, $workspace.'/'.$entry);
        }

        return $workspace;
    }

    private static function document(): string
    {
        return (string) file_get_contents(self::root().'/'.self::DOCUMENT);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
