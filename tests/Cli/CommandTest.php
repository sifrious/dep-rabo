<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Cli;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Cli\Application;
use Sifrious\Rabo\Tests\Fixture;

/**
 * The CLI's own behaviour, exercised in process.
 *
 * The commands `docs/human-verification.md` tells a reviewer to type are run separately, from that
 * document, by `tests/Docs/HumanVerificationTest.php`. This file covers what the CLI does with
 * arguments — including the ones a reviewer should never need to type.
 */
final class CommandTest extends TestCase
{
    private string $out = '';

    private string $err = '';

    public function test_validating_the_canonical_bundle_exits_zero_with_a_machine_readable_report(): void
    {
        $status = $this->rabo(['rabo', 'validate', Fixture::path()]);
        $report = json_decode($this->out, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $status);
        self::assertTrue($report['passed']);
        self::assertSame(0, $report['error_count']);
        self::assertSame('sifrious.rabo.validation-report', $report['contract']);
        self::assertStringContainsString('PASS', $this->err);
    }

    public function test_validating_a_broken_bundle_exits_non_zero_and_names_the_node(): void
    {
        $status = $this->rabo(['rabo', 'validate', $this->failing('unknown-brand-token')]);
        $report = json_decode($this->out, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $status);
        self::assertFalse($report['passed']);
        self::assertSame('RABO_BRAND_TOKEN_UNKNOWN', $report['issues'][0]['code']);
        self::assertSame('headline', $report['issues'][0]['path'], 'The report points at the exact node.');
        self::assertStringContainsString('RABO_BRAND_TOKEN_UNKNOWN', $this->err);
    }

    public function test_rendering_writes_the_artifact_and_its_provenance(): void
    {
        $directory = $this->workspace();

        $status = $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg', '--out='.$directory]);
        $result = json_decode($this->out, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $status);
        self::assertFileExists($directory.'/static.svg');
        self::assertFileExists($directory.'/static.provenance.json');
        self::assertSame($directory.'/static.svg', $result['artifact']);
        self::assertStringStartsWith('sha256:', $result['digest']);
    }

    public function test_a_render_that_would_be_invalid_writes_no_artifact(): void
    {
        $directory = $this->workspace();

        $status = $this->rabo(['rabo', 'render', $this->failing('insufficient-contrast'), '--format=svg', '--out='.$directory]);

        self::assertSame(1, $status);
        self::assertFileDoesNotExist($directory.'/static.svg', 'A refused render must leave nothing behind.');
        self::assertStringContainsString('RABO_CONTRAST_INSUFFICIENT', $this->err);
    }

    public function test_inspect_confirms_the_bytes_match_the_recorded_digest(): void
    {
        $directory = $this->workspace();
        $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg', '--out='.$directory]);

        $status = $this->rabo(['rabo', 'inspect', $directory.'/static.svg']);
        $result = json_decode($this->out, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $status);
        self::assertTrue($result['matches_provenance']);
        self::assertSame('burg', $result['provenance']['brand']['id']);
        self::assertSame('rabo-svg-static', $result['provenance']['renderer']['id']);
    }

    public function test_inspect_detects_an_artifact_that_was_edited_after_rendering(): void
    {
        $directory = $this->workspace();
        $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg', '--out='.$directory]);
        file_put_contents($directory.'/static.svg', '<!-- tampered -->', FILE_APPEND);

        $status = $this->rabo(['rabo', 'inspect', $directory.'/static.svg']);

        self::assertSame(1, $status);
        self::assertStringContainsString('do not match', $this->err);
    }

    public function test_rendering_the_square_variant_and_the_motion_pair_all_succeed(): void
    {
        $directory = $this->workspace();

        self::assertSame(0, $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg', '--scene=square', '--out='.$directory]));
        self::assertSame(0, $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg-animated', '--out='.$directory]));
        self::assertSame(0, $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg-animated', '--reduced-motion', '--out='.$directory]));

        foreach (['static-square.svg', 'motion.svg', 'reduced-motion.svg'] as $file) {
            self::assertFileExists($directory.'/'.$file);
            self::assertSame(
                file_get_contents(Fixture::path('expected/'.$file)),
                file_get_contents($directory.'/'.$file),
                "{$file} differs from the committed artifact.",
            );
        }
    }

    public function test_no_embed_fonts_leaves_the_typefaces_out(): void
    {
        $directory = $this->workspace();

        self::assertSame(0, $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg', '--out='.$directory]));
        $embedded = (string) file_get_contents($directory.'/static.svg');

        self::assertSame(0, $this->rabo(['rabo', 'render', Fixture::path(), '--format=svg', '--no-embed-fonts', '--out='.$directory, '--name=bare']));
        $bare = (string) file_get_contents($directory.'/bare.svg');

        self::assertSame(3, substr_count($embedded, '@font-face'));
        self::assertSame(0, substr_count($bare, '@font-face'), 'This flag was documented in three places and read by nothing.');
        self::assertLessThan(20_000, strlen($bare), 'docs/human-verification.md promises under 20 KB.');
        self::assertGreaterThan(100_000, strlen($embedded));
    }

    public function test_an_option_the_command_does_not_accept_is_refused(): void
    {
        self::assertSame(2, $this->rabo(['rabo', 'render', Fixture::path(), '--no-embed-font']));
        self::assertStringContainsString("Unknown option '--no-embed-font'", $this->err);
        self::assertStringContainsString('--no-embed-fonts', $this->err, 'The refusal lists what is accepted.');
    }

    public function test_a_flag_given_a_value_and_a_key_given_none_are_both_refused(): void
    {
        self::assertSame(2, $this->rabo(['rabo', 'render', Fixture::path(), '--reduced-motion=yes']));
        self::assertStringContainsString('is a flag and takes no value', $this->err);

        self::assertSame(2, $this->rabo(['rabo', 'render', Fixture::path(), '--format']));
        self::assertStringContainsString('needs a value', $this->err);
    }

    public function test_a_command_that_accepts_no_options_says_so(): void
    {
        self::assertSame(2, $this->rabo(['rabo', 'validate', Fixture::path(), '--format=svg']));
        self::assertStringContainsString('accepts no options', $this->err);
    }

    public function test_an_unknown_command_exits_two(): void
    {
        self::assertSame(2, $this->rabo(['rabo', 'sculpt']));
        self::assertStringContainsString('Unknown command', $this->err);
    }

    public function test_a_missing_bundle_exits_two_rather_than_crashing(): void
    {
        self::assertSame(2, $this->rabo(['rabo', 'validate', '/nowhere/at/all']));
        self::assertStringContainsString('No composition bundle', $this->err);
    }

    /** @param list<string> $argv */
    private function rabo(array $argv): int
    {
        $out = fopen('php://memory', 'r+');
        $err = fopen('php://memory', 'r+');
        $status = (new Application())->run($argv, $out, $err);
        rewind($out);
        rewind($err);
        $this->out = (string) stream_get_contents($out);
        $this->err = (string) stream_get_contents($err);
        fclose($out);
        fclose($err);

        return $status;
    }

    private function failing(string $name): string
    {
        return dirname(__DIR__, 2).'/fixtures/failing/'.$name;
    }

    private function workspace(): string
    {
        $directory = sys_get_temp_dir().'/rabo-cli-'.bin2hex(random_bytes(6));
        mkdir($directory, 0o775, true);

        return $directory;
    }
}
