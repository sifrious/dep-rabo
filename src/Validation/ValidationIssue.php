<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation;

use InvalidArgumentException;
use JsonSerializable;

/**
 * One structured finding.
 *
 * `path` names the exact thing at fault — a node identifier, or a dotted path into the
 * manifest — so a consumer can point at it rather than asking a person to go looking.
 */
final readonly class ValidationIssue implements JsonSerializable
{
    public function __construct(
        public IssueCode $code,
        public string $path,
        public string $message,
        public ?Severity $severity = null,
        public ?string $remediation = null,
    ) {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Validation issues must name the path they concern.');
        }
        if (trim($message) === '') {
            throw new InvalidArgumentException('Validation issues must carry a message.');
        }
    }

    public function severity(): Severity
    {
        return $this->severity ?? $this->code->defaultSeverity();
    }

    public function remediation(): string
    {
        return $this->remediation ?? $this->code->remediation();
    }

    public function isError(): bool
    {
        return $this->severity() === Severity::Error;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'severity' => $this->severity()->value,
            'path' => $this->path,
            'message' => $this->message,
            'remediation' => $this->remediation(),
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
