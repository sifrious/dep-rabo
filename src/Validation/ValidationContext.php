<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Composition;
use Sifrious\Rabo\Composition\Layout;
use Sifrious\Rabo\Composition\Node\ContainerNode;
use Sifrious\Rabo\Composition\Node\Node;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\Scene;
use Sifrious\Rabo\Motion\MotionComposition;
use Sifrious\Rabo\Render\RenderCapability;
use Sifrious\Rabo\Render\RenderTarget;

/** Everything a rule is allowed to look at. */
final class ValidationContext
{
    /** @var array<string,string>|null */
    private ?array $backgrounds = null;

    private ?Layout $layout = null;

    public function __construct(
        public readonly Composition $composition,
        public readonly BrandLibrary $brand,
        public readonly string $sceneName,
        public readonly Scene $scene,
        public readonly ?AssetStore $assets = null,
        public readonly ?MotionComposition $motion = null,
        public readonly ?RenderCapability $capability = null,
        public readonly ?RenderTarget $target = null,
    ) {}

    public function layout(): Layout
    {
        return $this->layout ??= $this->scene->layout($this->brand);
    }

    /**
     * The colour role a node is drawn on top of.
     *
     * Resolved by walking down from the scene background through each ancestor that declares
     * a fill, which is the same thing a viewer's eye does.
     */
    public function backgroundRoleFor(NodeId $id): ?string
    {
        if ($this->backgrounds === null) {
            $map = [];
            $this->collectBackgrounds($this->scene->root, $this->scene->background, $map);
            foreach ($this->scene->connectors as $connector) {
                $map[$connector->id()->value] = $this->scene->background ?? '';
            }
            $this->backgrounds = $map;
        }

        $role = $this->backgrounds[$id->value] ?? null;

        return $role === '' ? null : $role;
    }

    /** @param array<string,string> $map */
    private function collectBackgrounds(Node $node, ?string $inherited, array &$map): void
    {
        $own = $node->style()->fill ?? $inherited;
        $map[$node->id()->value] = $inherited ?? '';
        if ($node instanceof ContainerNode) {
            foreach ($node->children() as $child) {
                $this->collectBackgrounds($child, $own, $map);
            }
        }
    }
}
