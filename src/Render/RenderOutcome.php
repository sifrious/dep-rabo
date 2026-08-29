<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Validation\ValidationReport;

/**
 * What happened when a render was requested.
 *
 * Constructed only through the named factories, each of which fixes exactly which fields a
 * given status may carry. A refusal without a reason, or a success without provenance,
 * cannot be represented.
 */
final readonly class RenderOutcome implements JsonSerializable
{
    private function __construct(
        public RenderStatus $status,
        public ?RenderArtifact $artifact = null,
        public ?RenderProvenance $provenance = null,
        public ?ValidationReport $report = null,
        public ?string $code = null,
        public ?string $message = null,
        public ?int $retryAfterSeconds = null,
        public ?string $providerRequestId = null,
    ) {
        $valid = match ($status) {
            RenderStatus::Succeeded => $artifact !== null && $provenance !== null && $code === null,
            RenderStatus::Refused => $report !== null && $artifact === null,
            RenderStatus::FailedTransiently => $code !== null && $message !== null && $artifact === null,
            RenderStatus::Acknowledged => $providerRequestId !== null && $artifact === null,
        };
        if (! $valid) {
            throw new InvalidArgumentException('Render outcome fields do not match its status.');
        }
    }

    public static function succeeded(RenderArtifact $artifact, RenderProvenance $provenance): self
    {
        return new self(RenderStatus::Succeeded, artifact: $artifact, provenance: $provenance);
    }

    /** The request cannot be fulfilled as written. The report says exactly why. */
    public static function refused(ValidationReport $report): self
    {
        if ($report->passed()) {
            throw new InvalidArgumentException('A refusal must carry at least one blocking issue.');
        }

        return new self(RenderStatus::Refused, report: $report);
    }

    public static function failedTransiently(string $code, string $message, ?int $retryAfterSeconds = null): self
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Transient failure codes must be machine-readable tokens.');
        }

        return new self(RenderStatus::FailedTransiently, code: $code, message: $message, retryAfterSeconds: $retryAfterSeconds);
    }

    /** The provider accepted the work but returned no result. Resubmitting may duplicate it. */
    public static function acknowledged(string $providerRequestId, string $message = 'The renderer acknowledged the request without returning a result.'): self
    {
        if (trim($providerRequestId) === '') {
            throw new InvalidArgumentException('An acknowledgement must carry the provider request identifier.');
        }

        return new self(RenderStatus::Acknowledged, message: $message, providerRequestId: $providerRequestId);
    }

    public function isSuccess(): bool
    {
        return $this->status === RenderStatus::Succeeded;
    }

    /** The artifact, or an exception. Callers that want a maybe should read `artifact`. */
    public function artifactOrFail(): RenderArtifact
    {
        return $this->artifact ?? throw new InvalidArgumentException("No artifact was produced: the render {$this->status->value}.");
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'artifact' => $this->artifact?->toArray(),
            'provenance' => $this->provenance?->toArray(),
            'report' => $this->report?->toArray(),
            'code' => $this->code,
            'message' => $this->message,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'provider_request_id' => $this->providerRequestId,
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
