<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Renderer\Svg;

use Sifrious\Rabo\Asset\AssetStore;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Brand\FontFamily;
use Sifrious\Rabo\Brand\TextFlow;
use Sifrious\Rabo\Composition\Box;
use Sifrious\Rabo\Composition\Layout;
use Sifrious\Rabo\Composition\Node\ConnectorNode;
use Sifrious\Rabo\Composition\Node\ContainerNode;
use Sifrious\Rabo\Composition\Node\ImageNode;
use Sifrious\Rabo\Composition\Node\Node;
use Sifrious\Rabo\Composition\Node\ShapeKind;
use Sifrious\Rabo\Composition\Node\ShapeNode;
use Sifrious\Rabo\Composition\Node\StackNode;
use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Composition\Scene;
use Sifrious\Rabo\Composition\Alignment;

/**
 * Turns a laid-out scene into SVG body elements.
 *
 * Shared by the still and animated renderers so the two cannot drift: the reduced-motion
 * artifact and the first frame of the animation are painted by the same code.
 */
final readonly class ScenePainter
{
    private TextFlow $flow;

    public function __construct(
        private BrandLibrary $brand,
        private ?AssetStore $assets = null,
    ) {
        $this->flow = new TextFlow($brand->typography);
    }

    /** Every distinct stroke colour that needs an arrowhead marker. */
    /** @return list<string> */
    public function arrowheadColours(Scene $scene): array
    {
        $colours = [];
        foreach ($scene->connectors as $connector) {
            if (! $connector->arrowhead) {
                continue;
            }
            $role = $connector->style()->stroke;
            if ($role !== null && $this->brand->colors->hasRole($role)) {
                $hex = $this->brand->colors->resolveRole($role)->hex;
                $colours[$hex] = $hex;
            }
        }
        ksort($colours);

        return array_values($colours);
    }

    /**
     * Inlines the typefaces a scene actually uses.
     *
     * Without this an artifact renders in whatever the viewer happens to have, which for these
     * faces is usually a serif fallback that looks nothing like the brand. Only the families
     * behind type roles the scene sets are inlined, so an artifact carries no font it will not draw.
     *
     * Rasterizers that cannot read `@font-face` skip this block and match the next entry in each
     * font stack instead — see FontFamily::stack().
     */
    public function paintFontFaces(SvgDocument $svg, Scene $scene): void
    {
        $faces = [];
        foreach ($this->familiesUsedBy($scene) as $family) {
            $file = $family->embeddableFile();
            if ($file === null || $this->assets === null || ! $this->assets->has($file->digest)) {
                continue;
            }
            $faces[] = sprintf(
                "@font-face { font-family: '%s'; src: url(data:%s;base64,%s) format('%s'); }",
                $family->name,
                $file->format->mediaType(),
                base64_encode($this->assets->bytes($file->digest)),
                $file->format->cssFormat(),
            );
        }
        if ($faces === []) {
            return;
        }

        $svg->open('style');
        foreach ($faces as $face) {
            $svg->raw($face, 2);
        }
        $svg->close('style');
    }

    /** @return list<FontFamily> */
    public function familiesUsedBy(Scene $scene): array
    {
        $roles = [];
        foreach ($scene->nodes() as $node) {
            $role = $node->style()->typeRole;
            if ($role !== null) {
                $roles[$role] = $role;
            }
        }
        ksort($roles);

        return $this->brand->typography->familiesForRoles(array_values($roles));
    }

    public function paintBackground(SvgDocument $svg, Scene $scene): void
    {
        if ($scene->background === null || ! $this->brand->colors->hasRole($scene->background)) {
            return;
        }
        $svg->void('rect', [
            'x' => 0, 'y' => 0,
            'width' => $scene->canvas->width,
            'height' => $scene->canvas->height,
            'fill' => $this->brand->colors->resolveRole($scene->background)->hex,
        ]);
    }

    /**
     * Paints the node tree, keeping the composition's nesting in the output.
     *
     * Nesting is not cosmetic: a motion cue applied to a card has to carry the card's label
     * and detail with it. A flat list of sibling groups would animate the box and leave its
     * text behind.
     *
     * @param callable(Node):array<string,string|float|int|null> $groupAttributes
     */
    public function paintNodes(SvgDocument $svg, Layout $layout, ?callable $groupAttributes = null): void
    {
        $this->paintTree($svg, $layout->placed[0]->node ?? null, $layout, $groupAttributes, 1);
    }

    /** @param callable(Node):array<string,string|float|int|null>|null $groupAttributes */
    private function paintTree(SvgDocument $svg, ?Node $node, Layout $layout, ?callable $groupAttributes, int $depth): void
    {
        if ($node === null) {
            return;
        }
        $attributes = $groupAttributes === null ? [] : $groupAttributes($node);
        $body = $this->paintNode($node, $layout->box($node->id()));
        $children = $node instanceof ContainerNode ? $node->children() : [];

        if ($body === [] && $attributes === [] && $children === []) {
            return;
        }

        $svg->open('g', ['id' => (string) $node->id()] + $attributes, $depth);
        foreach ($body as $line) {
            $svg->raw($line, $depth + 1);
        }
        foreach ($children as $child) {
            $this->paintTree($svg, $child, $layout, $groupAttributes, $depth + 1);
        }
        $svg->close('g', $depth);
    }

    /** @param callable(ConnectorNode):array<string,string|float|int|null> $groupAttributes */
    public function paintConnectors(SvgDocument $svg, Scene $scene, Layout $layout, ?callable $groupAttributes = null): void
    {
        foreach ($scene->connectors as $connector) {
            $strokeRole = $connector->style()->stroke;
            if ($strokeRole === null || ! $this->brand->colors->hasRole($strokeRole)) {
                continue;
            }
            $hex = $this->brand->colors->resolveRole($strokeRole)->hex;
            $width = $connector->style()->strokeWidth === null ? 1.0 : $this->brand->strokes->step($connector->style()->strokeWidth);
            $path = $layout->connectorPath($connector);
            $inset = $connector->arrowhead ? 6.0 : 0.0;
            [$x2, $y2] = $this->shorten($path, $inset);

            $attributes = $groupAttributes === null ? [] : $groupAttributes($connector);
            $svg->open('g', ['id' => (string) $connector->id()] + $attributes);
            $line = '<line x1="'.SvgDocument::number($path['x1']).'" y1="'.SvgDocument::number($path['y1'])
                .'" x2="'.SvgDocument::number($x2).'" y2="'.SvgDocument::number($y2)
                .'" stroke="'.$hex.'" stroke-width="'.SvgDocument::number($width).'" stroke-linecap="round"'
                .($connector->arrowhead ? ' marker-end="url(#arrow-'.ltrim($hex, '#').')"' : '').'/>';
            $svg->raw($line, 2);
            if (($connector->textAlternative() ?? '') !== '') {
                $svg->element('title', $connector->textAlternative(), [], 2);
            }
            $svg->close('g');
        }
    }

    public function paintArrowheadDefs(SvgDocument $svg, Scene $scene): void
    {
        $colours = $this->arrowheadColours($scene);
        if ($colours === []) {
            return;
        }
        $svg->open('defs');
        foreach ($colours as $hex) {
            $svg->open('marker', [
                'id' => 'arrow-'.ltrim($hex, '#'),
                'viewBox' => '0 0 10 10', 'refX' => '8', 'refY' => '5',
                'markerWidth' => '6', 'markerHeight' => '6', 'orient' => 'auto-start-reverse',
            ], 2);
            $svg->void('path', ['d' => 'M 0 1 L 9 5 L 0 9 z', 'fill' => $hex], 3);
            $svg->close('marker', 2);
        }
        $svg->close('defs');
    }

    /** @return list<string> */
    private function paintNode(Node $node, Box $box): array
    {
        return match (true) {
            $node instanceof TextNode => $this->paintText($node, $box),
            $node instanceof ShapeNode => $this->paintShape($node, $box),
            $node instanceof ImageNode => $this->paintImage($node, $box),
            $node instanceof StackNode => $this->paintStackSurface($node, $box),
            default => [],
        };
    }

    /** @return list<string> */
    private function paintText(TextNode $node, Box $box): array
    {
        $role = $node->style()->typeRole;
        if ($role === null || ! $this->brand->typography->hasRole($role)) {
            return [];
        }
        $type = $this->brand->typography->role($role);
        $family = $this->brand->typography->family($type->family);
        $inkRole = $node->style()->text;
        $hex = $inkRole !== null && $this->brand->colors->hasRole($inkRole)
            ? $this->brand->colors->resolveRole($inkRole)->hex
            : '#000000';

        $lines = $this->flow->wrap($role, $node->content, $box->width, $node->maxLines);
        $anchor = match ($node->align) {
            Alignment::Start => 'start',
            Alignment::Center => 'middle',
            Alignment::End => 'end',
        };
        $x = match ($node->align) {
            Alignment::Start => $box->x,
            Alignment::Center => $box->centerX(),
            Alignment::End => $box->right(),
        };

        $out = [];
        foreach ($lines as $index => $line) {
            $baseline = $box->y + $type->lineHeightPx() * ($index + 0.78);
            $out[] = '<text x="'.SvgDocument::number($x).'" y="'.SvgDocument::number($baseline)
                .'" fill="'.$hex.'" font-family="'.SvgDocument::escape($family->stack()).'"'
                .' font-size="'.SvgDocument::number((float) $type->sizePx).'" font-weight="'.$type->weight.'"'
                .($type->tracking !== 0.0 ? ' letter-spacing="'.SvgDocument::number($type->tracking * $type->sizePx).'"' : '')
                .' text-anchor="'.$anchor.'" xml:space="preserve">'.SvgDocument::escape($line).'</text>';
        }

        return $out;
    }

    /** @return list<string> */
    private function paintShape(ShapeNode $node, Box $box): array
    {
        $fill = $this->fillHex($node->style()->fill);
        $stroke = $this->fillHex($node->style()->stroke);
        $width = $node->style()->strokeWidth === null ? null : $this->brand->strokes->step($node->style()->strokeWidth);
        $radius = $node->style()->radius === null ? null : $this->brand->radii->step($node->style()->radius);

        if ($node->shape === ShapeKind::Ellipse) {
            return ['<ellipse cx="'.SvgDocument::number($box->centerX()).'" cy="'.SvgDocument::number($box->centerY())
                .'" rx="'.SvgDocument::number($box->width / 2).'" ry="'.SvgDocument::number($box->height / 2).'"'
                .$this->paintAttributes($fill, $stroke, $width).'/>'];
        }

        return ['<rect x="'.SvgDocument::number($box->x).'" y="'.SvgDocument::number($box->y)
            .'" width="'.SvgDocument::number($box->width).'" height="'.SvgDocument::number($box->height).'"'
            .($radius === null ? '' : ' rx="'.SvgDocument::number($radius).'"')
            .$this->paintAttributes($fill, $stroke, $width).'/>'];
    }

    /** A stack draws a surface only when the brand gave it one. */
    /** @return list<string> */
    private function paintStackSurface(StackNode $node, Box $box): array
    {
        if ($node->style()->fill === null && $node->style()->stroke === null) {
            return [];
        }
        $radius = $node->style()->radius === null ? null : $this->brand->radii->step($node->style()->radius);
        $width = $node->style()->strokeWidth === null ? null : $this->brand->strokes->step($node->style()->strokeWidth);

        return ['<rect x="'.SvgDocument::number($box->x).'" y="'.SvgDocument::number($box->y)
            .'" width="'.SvgDocument::number($box->width).'" height="'.SvgDocument::number($box->height).'"'
            .($radius === null ? '' : ' rx="'.SvgDocument::number($radius).'"')
            .$this->paintAttributes($this->fillHex($node->style()->fill), $this->fillHex($node->style()->stroke), $width).'/>'];
    }

    /** @return list<string> */
    private function paintImage(ImageNode $node, Box $box): array
    {
        if ($this->assets === null || ! $this->assets->has($node->asset)) {
            return [];
        }
        $bytes = $this->assets->bytes($node->asset);
        $href = 'data:image/svg+xml;base64,'.base64_encode($bytes);
        $title = $node->textAlternative();

        $image = '<image x="'.SvgDocument::number($box->x).'" y="'.SvgDocument::number($box->y)
            .'" width="'.SvgDocument::number($box->width).'" height="'.SvgDocument::number($box->height)
            .'" href="'.$href.'"'.($title === null ? '' : ' aria-label="'.SvgDocument::escape($title).'"').'/>';

        return $title === null ? [$image] : ['<title>'.SvgDocument::escape($title).'</title>', $image];
    }

    private function paintAttributes(?string $fill, ?string $stroke, ?float $width): string
    {
        return ' fill="'.($fill ?? 'none').'"'
            .($stroke === null ? '' : ' stroke="'.$stroke.'" stroke-width="'.SvgDocument::number($width ?? 1.0).'"');
    }

    private function fillHex(?string $role): ?string
    {
        return $role !== null && $this->brand->colors->hasRole($role)
            ? $this->brand->colors->resolveRole($role)->hex
            : null;
    }

    /** @param array{x1:float,y1:float,x2:float,y2:float} $path */
    /** @return array{0:float,1:float} */
    private function shorten(array $path, float $by): array
    {
        $dx = $path['x2'] - $path['x1'];
        $dy = $path['y2'] - $path['y1'];
        $length = sqrt($dx * $dx + $dy * $dy);
        if ($length <= $by || $length === 0.0) {
            return [$path['x2'], $path['y2']];
        }
        $scale = ($length - $by) / $length;

        return [$path['x1'] + $dx * $scale, $path['y1'] + $dy * $scale];
    }
}
