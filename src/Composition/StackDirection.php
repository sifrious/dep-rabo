<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

enum StackDirection: string
{
    case Horizontal = 'horizontal';
    case Vertical = 'vertical';

    public function opposite(): self
    {
        return $this === self::Horizontal ? self::Vertical : self::Horizontal;
    }
}
