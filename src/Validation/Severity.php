<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation;

enum Severity: string
{
    /** The composition may not be rendered or approved in this state. */
    case Error = 'error';

    /** Worth a person's attention; does not block. */
    case Warning = 'warning';
}
