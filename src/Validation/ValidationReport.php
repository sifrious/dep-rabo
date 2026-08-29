<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation;

use JsonSerializable;

/**
 * The result of validating one composition.
 *
 * Ordered deterministically — by code, then path — so two runs over the same input produce
 * the same report, and a report can be diffed between commits.
 */
final readonly class ValidationReport implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.validation-report';

    public const CONTRACT_VERSION = 1;

    /** @var list<ValidationIssue> */
    public array $issues;

    /** @param list<ValidationIssue> $issues */
    public function __construct(array $issues = [])
    {
        $sorted = array_values($issues);
        usort($sorted, static fn (ValidationIssue $a, ValidationIssue $b): int => [$a->code->value, $a->path, $a->message] <=> [$b->code->value, $b->path, $b->message]);
        $this->issues = $sorted;
    }

    public function merge(self $other): self
    {
        return new self([...$this->issues, ...$other->issues]);
    }

    /** True when nothing blocks. Warnings do not fail a composition. */
    public function passed(): bool
    {
        return $this->errors() === [];
    }

    /** @return list<ValidationIssue> */
    public function errors(): array
    {
        return array_values(array_filter($this->issues, static fn (ValidationIssue $i): bool => $i->isError()));
    }

    /** @return list<ValidationIssue> */
    public function warnings(): array
    {
        return array_values(array_filter($this->issues, static fn (ValidationIssue $i): bool => ! $i->isError()));
    }

    public function has(IssueCode $code): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->code === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return list<ValidationIssue> */
    public function withCode(IssueCode $code): array
    {
        return array_values(array_filter($this->issues, static fn (ValidationIssue $i): bool => $i->code === $code));
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_values(array_unique(array_map(static fn (ValidationIssue $i): string => $i->code->value, $this->issues)));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'passed' => $this->passed(),
            'error_count' => count($this->errors()),
            'warning_count' => count($this->warnings()),
            'issues' => array_map(static fn (ValidationIssue $i): array => $i->toArray(), $this->issues),
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
