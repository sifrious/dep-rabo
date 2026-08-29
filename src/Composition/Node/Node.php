<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Composition\Node;

use JsonSerializable;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\NodeStyle;
use Sifrious\Rabo\Composition\Size;

/**
 * A composition primitive.
 *
 * The set is deliberately small. Rabo describes diagrams and content graphics, not an
 * unbounded design surface; a primitive earns its place by being needed for a real
 * composition, not by being present in a design tool.
 */
interface Node extends JsonSerializable
{
    public function id(): NodeId;

    /** The wire discriminator, used to reconstruct the node. */
    public function type(): string;

    public function style(): NodeStyle;

    /**
     * The size the node declares for itself, or null when its container computes it.
     *
     * Containers cannot answer this alone: their extent depends on brand-declared gaps and
     * padding, which only the layout pass has in hand.
     */
    public function declaredSize(): ?Size;

    /** Text an assistive reader should receive in place of, or alongside, the visual. */
    public function textAlternative(): ?string;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
