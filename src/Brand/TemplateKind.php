<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

enum TemplateKind: string
{
    /** An editable composition for a non-temporal output. */
    case StaticTemplate = 'static';

    /** An editable composition with a timeline. */
    case SceneTemplate = 'scene';
}
