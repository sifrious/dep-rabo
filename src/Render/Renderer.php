<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Render;

/**
 * The boundary between a composition and whatever draws it.
 *
 * Rabo does not know whether an implementation writes SVG, drives a browser, shells out to
 * an encoder, or calls a hosted model. It knows only that a renderer declares what it can
 * do and returns one of four outcomes.
 */
interface Renderer
{
    public function capabilities(): RenderCapability;

    public function render(RenderRequest $request): RenderOutcome;
}
