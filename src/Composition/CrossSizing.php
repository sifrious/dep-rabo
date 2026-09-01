<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition;

/**
 * How a stack sizes its children across the axis it does not stack along.
 *
 * `Hug` is the original behaviour and the default: every child keeps the extent it measures, so a
 * stack is only ever as wide as its widest child. That is right until a variant re-orients it. Two
 * 560-wide columns sitting edge to edge in a landscape scene become a single 560-wide column in a
 * 1000-wide portrait, leaving 440px of dead space beside content that is correct and legal and
 * simply does not use the room it was given — see Q-008.
 *
 * `Fill` gives a child the whole of the stack's inner cross extent. It applies only to children
 * that declare no size of their own, which is exactly the set whose extent was always the stack's
 * to compute: `Node::declaredSize()` returns null "when its container computes it". An authored
 * size is never overridden, so this cannot move a text box and cannot make validation and drawing
 * disagree about where a word goes.
 *
 * A filled child has no alignment left to have — it occupies the whole track — so `Fill`
 * supersedes the stack's `align` for those children while leaving it in force for any sibling that
 * declares a size.
 */
enum CrossSizing: string
{
    case Hug = 'hug';

    case Fill = 'fill';
}
