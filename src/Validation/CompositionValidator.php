<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation;

use Sifrious\Rabo\Asset\Asset;
use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Composition;
use Sifrious\Rabo\Render\RenderCapability;
use Sifrious\Rabo\Render\RenderTarget;
use Sifrious\Rabo\Validation\Rule\AccessibilityRule;
use Sifrious\Rabo\Validation\Rule\AssetRightsRule;
use Sifrious\Rabo\Validation\Rule\AssetRule;
use Sifrious\Rabo\Validation\Rule\BrandCompatibilityRule;
use Sifrious\Rabo\Validation\Rule\BrandTokenRule;
use Sifrious\Rabo\Validation\Rule\ContrastRule;
use Sifrious\Rabo\Validation\Rule\DimensionsRule;
use Sifrious\Rabo\Validation\Rule\MarkTreatmentRule;
use Sifrious\Rabo\Validation\Rule\ReferenceRule;
use Sifrious\Rabo\Validation\Rule\RendererCapabilityRule;
use Sifrious\Rabo\Validation\Rule\TextOverflowRule;

/**
 * Runs every rule and returns one ordered report.
 *
 * No model, no network, no clock. Determinism is the point: a validation result is only
 * worth gating a release on if the same input always produces it.
 */
final readonly class CompositionValidator
{
    /** @var list<Rule> */
    private array $rules;

    /** @param list<Rule>|null $rules */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? self::defaultRules();
    }

    /** @return list<Rule> */
    public static function defaultRules(): array
    {
        return [
            new BrandCompatibilityRule(),
            new BrandTokenRule(),
            new ContrastRule(),
            new TextOverflowRule(),
            new DimensionsRule(),
            new AccessibilityRule(),
            new MarkTreatmentRule(),
            new AssetRule(),
            new ReferenceRule(),
            new RendererCapabilityRule(),
        ];
    }

    /** Validates the canonical scene and every derived variant. */
    public function validate(
        Composition $composition,
        BrandLibrary $brand,
        ?AssetStore $assets = null,
        ?RenderCapability $capability = null,
        ?RenderTarget $target = null,
        /** @var array<string,Asset> */
        array $knownAssets = [],
    ): ValidationReport {
        $report = new ValidationReport();
        $rules = $knownAssets === [] ? $this->rules : [...$this->rules, new AssetRightsRule($knownAssets)];

        foreach ($composition->allScenes() as $sceneName => $scene) {
            $context = new ValidationContext(
                $composition,
                $brand,
                $sceneName,
                $scene,
                $assets,
                null,
                $capability,
                $sceneName === 'source' ? $target : null,
            );
            foreach ($rules as $rule) {
                $issues = array_map(
                    static fn (ValidationIssue $issue): ValidationIssue => new ValidationIssue(
                        $issue->code,
                        $sceneName === 'source' ? $issue->path : $sceneName.'/'.$issue->path,
                        $issue->message,
                        $issue->severity,
                        $issue->remediation,
                    ),
                    $rule->check($context),
                );
                $report = $report->merge(new ValidationReport($issues));
            }
        }

        return $report;
    }

    /** Validates one named scene only. */
    public function validateScene(ValidationContext $context): ValidationReport
    {
        $report = new ValidationReport();
        foreach ($this->rules as $rule) {
            $report = $report->merge(new ValidationReport($rule->check($context)));
        }

        return $report;
    }
}
