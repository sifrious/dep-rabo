<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation;

/**
 * One deterministic check.
 *
 * Rules take no clock, no network, and no model. The same composition and brand always
 * produce the same findings, which is what makes a failed validation something a build can
 * be gated on.
 */
interface Rule
{
    public function name(): string;

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array;
}
