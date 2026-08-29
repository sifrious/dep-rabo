<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

/**
 * The four things that can come back from asking for a render.
 *
 * These are kept apart because a caller must treat them differently. A refusal will never
 * succeed on retry; a transient failure probably will; an acknowledgement means the caller
 * does not yet know whether anything happened, and retrying it may duplicate work.
 */
enum RenderStatus: string
{
    case Succeeded = 'succeeded';

    /** Deterministic: the request is wrong and will stay wrong. */
    case Refused = 'refused';

    /** Something outside the request failed. The same request may succeed later. */
    case FailedTransiently = 'failed_transiently';

    /** The provider took the work but did not say what happened. */
    case Acknowledged = 'acknowledged';

    public function isRetryable(): bool
    {
        return $this === self::FailedTransiently;
    }

    /** Whether a caller may safely resubmit without risking duplicate work. */
    public function isSafeToRetry(): bool
    {
        return $this === self::FailedTransiently;
    }
}
