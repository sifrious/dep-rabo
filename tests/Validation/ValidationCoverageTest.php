<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Tests\Fixture;
use Sifrious\Rabo\Validation\CompositionValidator;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule\MotionRule;
use Sifrious\Rabo\Validation\Severity;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;
use Sifrious\Rabo\Validation\ValidationReport;

final class ValidationCoverageTest extends TestCase
{
    /**
     * One small bundle per validation dimension, each built to break exactly one rule.
     *
     * @return array<string,IssueCode>
     */
    private const FAILING_BUNDLES = [
        'unknown-brand-token' => IssueCode::BrandTokenUnknown,
        'insufficient-contrast' => IssueCode::ContrastInsufficient,
        'text-overflow' => IssueCode::TextOverflow,
        'missing-asset' => IssueCode::AssetMissing,
        'missing-text-alternative' => IssueCode::TextAlternativeMissing,
        'incomplete-reading-order' => IssueCode::ReadingOrderIncomplete,
        'missing-evidence-reference' => IssueCode::ReferenceRequiredMissing,
        'content-exceeds-canvas' => IssueCode::DimensionsInvalid,
        'brand-drift' => IssueCode::BrandDrift,
        'motion-cue-past-end' => IssueCode::MotionDurationInvalid,
        'motion-cue-overlap' => IssueCode::MotionCueOverlapUnresolved,
        'motion-essential-dismissed' => IssueCode::MotionInformationOnlyTransient,
        'missing-font-asset' => IssueCode::FontAssetMissing,
        'unreadable-font-asset' => IssueCode::FontAssetUnreadable,
    ];

    public function test_each_failing_bundle_reports_its_own_code_and_nothing_else(): void
    {
        foreach (self::FAILING_BUNDLES as $directory => $expected) {
            $report = $this->validate($directory);

            self::assertFalse($report->passed(), "Bundle '{$directory}' was expected to fail validation.");
            // Blocking codes only. These minimal brands ship no font files, which warns; that is
            // correct behaviour and not a second dimension under test.
            self::assertSame([$expected->value], $report->errorCodes(), "Bundle '{$directory}' should isolate one blocking dimension.");
            self::assertNotSame('', $report->withCode($expected)[0]->path, "Bundle '{$directory}' must name the thing at fault.");
            self::assertNotSame('', $report->withCode($expected)[0]->remediation());
        }
    }

    /**
     * The other direction, which is the one that went wrong.
     *
     * The constant was checked against the filesystem but never the filesystem against the
     * constant, so `unreadable-font-asset` sat there covered by nothing, and three documents
     * disagreed about how many bundles existed — "thirteen", "twelve" and "twelve", against
     * fourteen on disk.
     */
    public function test_no_failing_fixture_sits_outside_the_map(): void
    {
        $directories = glob(dirname(__DIR__, 2).'/fixtures/failing/*', GLOB_ONLYDIR) ?: [];
        self::assertNotEmpty($directories);

        $unmapped = array_values(array_diff(
            array_map(basename(...), $directories),
            array_keys(self::FAILING_BUNDLES),
        ));

        self::assertSame([], $unmapped, 'These bundles exist but no test asserts what they isolate: '.implode(', ', $unmapped));
    }

    public function test_a_fixture_exists_for_every_dimension_the_slice_depends_on(): void
    {
        foreach (array_keys(self::FAILING_BUNDLES) as $directory) {
            self::assertDirectoryExists(dirname(__DIR__, 2).'/fixtures/failing/'.$directory);
        }
    }

    public function test_the_canonical_bundle_passes_every_rule(): void
    {
        $report = $this->validate('../'.Fixture::SLICE);

        self::assertTrue($report->passed(), 'Canonical fixture errors: '.implode(', ', $report->errorCodes()));
        self::assertSame([], $report->errors());
    }

    /**
     * The canonical composition has one honest defect, and validation says so rather than hiding it.
     *
     * Its headline turns on "≠", and every one of the brand's three faces is a latin subset that
     * has no glyph for U+2260. That character therefore comes from whatever the viewer happens to
     * have installed, and from nothing at all on a machine with no such font. The headline text is
     * fixed by the brief, so the honest end state is a warning that names the character rather than
     * a quiet substitution.
     */
    public function test_the_canonical_bundle_warns_that_its_headline_glyph_is_not_in_the_brand(): void
    {
        $report = $this->validate('../'.Fixture::SLICE);

        self::assertSame([IssueCode::FontGlyphUnavailable->value], $report->codes());
        self::assertTrue($report->passed(), 'A missing glyph is worth reading, not worth blocking on.');

        $issue = $report->withCode(IssueCode::FontGlyphUnavailable)[0];
        self::assertStringContainsString('≠', $issue->message);
        self::assertSame('headline', $issue->path);
        self::assertSame(Severity::Warning, $issue->severity());
    }

    public function test_an_unsatisfiable_brand_pin_blocks_rather_than_warns(): void
    {
        $issues = $this->validate('brand-drift')->withCode(IssueCode::BrandDrift);

        self::assertCount(1, $issues);
        self::assertSame(Severity::Error, $issues[0]->severity());
    }

    public function test_reports_are_ordered_deterministically(): void
    {
        $issues = [
            new ValidationIssue(IssueCode::TextOverflow, 'z-node', 'Later.'),
            new ValidationIssue(IssueCode::AssetMissing, 'a-node', 'Earlier.'),
            new ValidationIssue(IssueCode::TextOverflow, 'a-node', 'Middle.'),
        ];

        $forward = new ValidationReport($issues);
        $backward = new ValidationReport(array_reverse($issues));

        self::assertSame($forward->toArray(), $backward->toArray(), 'Issue order must not depend on discovery order.');
        self::assertSame('a-node', $forward->issues[0]->path);
    }

    public function test_warnings_do_not_block_but_are_still_reported(): void
    {
        $report = new ValidationReport([
            new ValidationIssue(IssueCode::BrandDrift, 'brand', 'Out of date.', Severity::Warning),
        ]);

        self::assertTrue($report->passed());
        self::assertCount(1, $report->warnings());
        self::assertCount(0, $report->errors());
    }

    public function test_every_issue_code_carries_a_remediation_hint(): void
    {
        foreach (IssueCode::cases() as $code) {
            self::assertNotSame('', trim($code->remediation()), "{$code->value} has no remediation hint.");
        }
    }

    private function validate(string $directory): ValidationReport
    {
        $bundle = CompositionBundle::load(dirname(__DIR__, 2).'/fixtures/failing/'.$directory);
        $report = (new CompositionValidator())->validate(
            $bundle->composition,
            $bundle->brand,
            $bundle->assets,
            null,
            null,
            $bundle->assetRecords,
        );

        if ($bundle->hasMotion()) {
            $motion = $bundle->motionOrFail();
            $report = $report->merge(new ValidationReport((new MotionRule())->check(new ValidationContext(
                $bundle->composition,
                $bundle->brand,
                $motion->scene,
                $bundle->composition->allScenes()[$motion->scene],
                $bundle->assets,
                $motion,
            ))));
        }

        return $report;
    }
}
