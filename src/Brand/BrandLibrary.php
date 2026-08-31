<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Brand;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Rabo\Asset\ContentDigest;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * The portable source of truth for a customer's visual identity.
 *
 * A Brand Library is a document, not a database row. It carries everything a renderer needs
 * — colours, type, spacing, marks, motion, and the accessibility floor — so a composition
 * can be rendered by a process that has never seen the application that authored it.
 *
 * It references assets by content digest and never by path, and it declares which renderers
 * it is known to work with rather than naming one. Product-interface design systems are a
 * different thing owned elsewhere; nothing here is Burdgen's theme model.
 */
final readonly class BrandLibrary implements JsonSerializable
{
    public const CONTRACT = 'sifrious.rabo.brand-library';

    public const CONTRACT_VERSION = 1;

    /** @var array<string,Mark> */
    public array $marks;

    /** @var array<string,Template> */
    public array $templates;

    /** @var list<string> */
    public array $compatibleRenderers;

    /**
     * @param  list<Mark>  $marks
     * @param  list<Template>  $templates
     * @param  list<string>  $compatibleRenderers
     */
    public function __construct(
        public string $id,
        public BrandVersion $version,
        public string $name,
        public ColorSystem $colors,
        public TypographySystem $typography,
        public NumericScale $spacing,
        public NumericScale $radii,
        public NumericScale $strokes,
        public MotionTokens $motion,
        public AccessibilityRules $accessibility,
        array $marks = [],
        array $templates = [],
        array $compatibleRenderers = [],
        public ?CrossPackageReference $provenance = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Brand identifiers must be lowercase kebab-case.');
        }
        if (trim($name) === '') {
            throw new InvalidArgumentException('Brand libraries must carry a name.');
        }

        $indexedMarks = [];
        foreach ($marks as $mark) {
            if (! $mark instanceof Mark) {
                throw new InvalidArgumentException('Brand libraries accept Mark values only.');
            }
            if (isset($indexedMarks[$mark->id])) {
                throw new InvalidArgumentException("Mark '{$mark->id}' is declared twice.");
            }
            $indexedMarks[$mark->id] = $mark;
        }
        ksort($indexedMarks);

        $indexedTemplates = [];
        foreach ($templates as $template) {
            if (! $template instanceof Template) {
                throw new InvalidArgumentException('Brand libraries accept Template values only.');
            }
            if (isset($indexedTemplates[$template->id])) {
                throw new InvalidArgumentException("Template '{$template->id}' is declared twice.");
            }
            $indexedTemplates[$template->id] = $template;
        }
        ksort($indexedTemplates);

        foreach ($compatibleRenderers as $renderer) {
            if (! is_string($renderer) || preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $renderer) !== 1) {
                throw new InvalidArgumentException('Compatible renderer names must be lowercase kebab-case.');
            }
        }
        sort($compatibleRenderers);

        $this->marks = $indexedMarks;
        $this->templates = $indexedTemplates;
        $this->compatibleRenderers = array_values($compatibleRenderers);
    }

    public function mark(string $id): Mark
    {
        return $this->marks[$id] ?? throw new UnknownBrandToken("The brand declares no mark '{$id}'.");
    }

    public function template(string $id): Template
    {
        return $this->templates[$id] ?? throw new UnknownBrandToken("The brand declares no template '{$id}'.");
    }

    public function declaresRendererCompatibility(string $renderer): bool
    {
        return $this->compatibleRenderers === [] || in_array($renderer, $this->compatibleRenderers, true);
    }

    /** Every asset digest this brand depends on, in a stable order. */
    /** @return list<ContentDigest> */
    public function referencedAssets(): array
    {
        $digests = [];
        foreach ($this->marks as $mark) {
            foreach ($mark->assets() as $digest) {
                $digests[(string) $digest] = $digest;
            }
        }
        foreach ($this->typography->families as $family) {
            foreach ($family->assets() as $digest) {
                $digests[(string) $digest] = $digest;
            }
        }
        ksort($digests);

        return array_values($digests);
    }

    /** A stable identity for this exact manifest content. */
    public function key(): string
    {
        return hash('sha256', $this->canonical());
    }

    /** The canonical serialization. Maps are emitted as JSON objects, so compare on this
     * rather than on toArray(), whose stdClass maps are never identical by ===. */
    public function canonical(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function equals(self $other): bool
    {
        return $this->canonical() === $other->canonical();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'id' => $this->id,
            'version' => (string) $this->version,
            'name' => $this->name,
            'colors' => $this->colors->toArray(),
            'typography' => $this->typography->toArray(),
            'spacing' => $this->spacing->toArray(),
            'radii' => $this->radii->toArray(),
            'strokes' => $this->strokes->toArray(),
            'motion' => $this->motion->toArray(),
            'accessibility' => $this->accessibility->toArray(),
            'marks' => array_values(array_map(static fn (Mark $m): array => $m->toArray(), $this->marks)),
            'templates' => array_values(array_map(static fn (Template $t): array => $t->toArray(), $this->templates)),
            'compatible_renderers' => $this->compatibleRenderers,
            'provenance' => $this->provenance?->toArray(),
        ];
    }

    /** @param array<string,mixed> $serialized */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT) {
            throw new InvalidArgumentException('Unsupported Rabo brand library contract.');
        }
        if (($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported Rabo brand library contract version.');
        }
        $id = $serialized['id'] ?? null;
        $version = $serialized['version'] ?? null;
        $name = $serialized['name'] ?? null;
        if (! is_string($id) || ! is_string($version) || ! is_string($name)) {
            throw new InvalidArgumentException('Serialized brand libraries require id, version, and name.');
        }
        foreach (['colors', 'typography', 'spacing', 'radii', 'strokes', 'motion', 'accessibility'] as $required) {
            if (! is_array($serialized[$required] ?? null)) {
                throw new InvalidArgumentException("Serialized brand libraries require a {$required} section.");
            }
        }
        $marks = $serialized['marks'] ?? [];
        $templates = $serialized['templates'] ?? [];
        $renderers = $serialized['compatible_renderers'] ?? [];
        $provenance = $serialized['provenance'] ?? null;
        if (! is_array($marks) || ! is_array($templates) || ! is_array($renderers)) {
            throw new InvalidArgumentException('Serialized brand libraries require marks, templates, and compatible_renderers arrays.');
        }

        /** @var array<string,mixed> $colors */
        $colors = $serialized['colors'];
        /** @var array<string,mixed> $typography */
        $typography = $serialized['typography'];
        /** @var array<string,mixed> $spacing */
        $spacing = $serialized['spacing'];
        /** @var array<string,mixed> $radii */
        $radii = $serialized['radii'];
        /** @var array<string,mixed> $strokes */
        $strokes = $serialized['strokes'];
        /** @var array<string,mixed> $motion */
        $motion = $serialized['motion'];
        /** @var array<string,mixed> $accessibility */
        $accessibility = $serialized['accessibility'];

        return new self(
            $id,
            BrandVersion::parse($version),
            $name,
            ColorSystem::fromArray($colors),
            TypographySystem::fromArray($typography),
            NumericScale::fromArray($spacing),
            NumericScale::fromArray($radii),
            NumericScale::fromArray($strokes),
            MotionTokens::fromArray($motion),
            AccessibilityRules::fromArray($accessibility),
            array_map(static function (mixed $m): Mark {
                if (! is_array($m)) {
                    throw new InvalidArgumentException('Serialized marks must be objects.');
                }

                return Mark::fromArray($m);
            }, array_values($marks)),
            array_map(static function (mixed $t): Template {
                if (! is_array($t)) {
                    throw new InvalidArgumentException('Serialized templates must be objects.');
                }

                return Template::fromArray($t);
            }, array_values($templates)),
            array_values(array_map(strval(...), $renderers)),
            is_array($provenance) ? CrossPackageReference::fromArray($provenance) : null,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
