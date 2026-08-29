<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Composition;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Brand\BrandLibrary;
use Sifrious\Rabo\Composition\Composition;
use Sifrious\Rabo\Composition\Node\NodeFactory;
use Sifrious\Rabo\Composition\Node\StackNode;
use Sifrious\Rabo\Composition\NodeId;
use Sifrious\Rabo\Composition\StackDirection;
use Sifrious\Rabo\Composition\UnsupportedNode;
use Sifrious\Rabo\Tests\Fixture;

final class CompositionContractTest extends TestCase
{
    public function test_the_canonical_composition_parses(): void
    {
        $composition = Composition::fromArray(Fixture::json('composition.json'));

        self::assertSame('agent-completion-verified-completion', $composition->id);
        self::assertSame(1, $composition->version);
        self::assertSame('burg', $composition->brandId);
        self::assertSame(1200, $composition->scene->canvas->width);
        self::assertSame(630, $composition->scene->canvas->height);
        self::assertSame('Agent completion ≠ verified completion', $this->headline($composition));
    }

    public function test_serialization_is_stable(): void
    {
        $composition = Composition::fromArray(Fixture::json('composition.json'));
        $restored = Composition::fromArray(json_decode($composition->canonical(), true, flags: JSON_THROW_ON_ERROR));

        self::assertTrue($restored->equals($composition));
        self::assertSame($composition->key(), $restored->key());
    }

    public function test_an_unknown_primitive_fails_cleanly_rather_than_being_skipped(): void
    {
        $this->expectException(UnsupportedNode::class);
        $this->expectExceptionMessage("no composition primitive of type 'hologram'");

        NodeFactory::fromArray(['type' => 'hologram', 'id' => 'x']);
    }

    public function test_node_order_is_deterministic_across_reloads(): void
    {
        $first = Composition::fromArray(Fixture::json('composition.json'));
        $second = Composition::fromArray(Fixture::json('composition.json'));

        $ids = static fn (Composition $c): array => array_map(static fn ($n): string => (string) $n->id(), $c->scene->nodes());

        self::assertSame($ids($first), $ids($second));
        self::assertSame('root', $ids($first)[0], 'Parents are visited before their children.');
    }

    public function test_a_variant_is_derived_without_replacing_the_source_scene(): void
    {
        $composition = Composition::fromArray(Fixture::json('composition.json'));
        $sourceKeyBefore = $composition->scene->key();

        $square = $composition->variantScene('square');

        self::assertSame($sourceKeyBefore, $composition->scene->key(), 'Deriving a variant must not mutate the source.');
        self::assertSame(1080, $square->canvas->width);
        self::assertSame(1080, $square->canvas->height);
        self::assertNotSame($composition->scene->id, $square->id);

        $sourceFlow = $composition->scene->findNode(new NodeId('flow'));
        $variantFlow = $square->findNode(new NodeId('flow'));
        self::assertInstanceOf(StackNode::class, $sourceFlow);
        self::assertInstanceOf(StackNode::class, $variantFlow);
        self::assertSame(StackDirection::Horizontal, $sourceFlow->direction);
        self::assertSame(StackDirection::Vertical, $variantFlow->direction, 'The variant flips the flow axis.');
    }

    public function test_a_variant_carries_the_same_content_as_its_source(): void
    {
        $composition = Composition::fromArray(Fixture::json('composition.json'));

        $text = static function ($scene): array {
            $contents = [];
            foreach ($scene->nodes() as $node) {
                if (property_exists($node, 'content')) {
                    $contents[(string) $node->id()] = $node->content;
                }
            }

            return $contents;
        };

        self::assertSame($text($composition->scene), $text($composition->variantScene('square')),
            'A variant is a re-flow, never a second piece of copy that can drift.');
    }

    public function test_both_variants_lay_out_inside_their_canvas(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));
        $composition = Composition::fromArray(Fixture::json('composition.json'));

        foreach ($composition->allScenes() as $name => $scene) {
            $root = $scene->layout($brand)->box(new NodeId('root'));

            self::assertGreaterThanOrEqual(0.0, $root->x, "Variant '{$name}' overflows the left edge.");
            self::assertGreaterThanOrEqual(0.0, $root->y, "Variant '{$name}' overflows the top edge.");
            self::assertLessThanOrEqual((float) $scene->canvas->width, $root->right(), "Variant '{$name}' overflows the right edge.");
            self::assertLessThanOrEqual((float) $scene->canvas->height, $root->bottom(), "Variant '{$name}' overflows the bottom edge.");
        }
    }

    public function test_layout_is_a_pure_function_of_scene_and_brand(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));
        $scene = Composition::fromArray(Fixture::json('composition.json'))->scene;

        $boxes = static fn (): array => array_map(
            static fn ($placed): array => $placed->box->toArray(),
            $scene->layout($brand)->placed,
        );

        self::assertSame($boxes(), $boxes());
    }

    public function test_connectors_follow_the_axis_the_variant_produced(): void
    {
        $brand = BrandLibrary::fromArray(Fixture::json('brand.json'));
        $composition = Composition::fromArray(Fixture::json('composition.json'));

        $landscape = $composition->scene->layout($brand);
        $square = $composition->variantScene('square')->layout($brand);
        $connector = $composition->scene->connectors[0];

        $wide = $landscape->connectorPath($connector);
        $tall = $square->connectorPath($connector);

        self::assertSame($wide['y1'], $wide['y2'], 'In the landscape flow the arrow runs horizontally.');
        self::assertSame($tall['x1'], $tall['x2'], 'In the square flow the same arrow runs vertically.');
    }

    public function test_a_connector_naming_an_unknown_node_is_rejected(): void
    {
        $serialized = Fixture::json('composition.json');
        $serialized['scene']['connectors'][0]['to'] = 'no-such-node';

        $this->expectException(InvalidArgumentException::class);

        Composition::fromArray($serialized);
    }

    public function test_the_reading_order_is_authored_and_covers_every_essential_text(): void
    {
        $composition = Composition::fromArray(Fixture::json('composition.json'));
        $order = array_map(strval(...), $composition->scene->readingOrder);

        self::assertSame('eyebrow', $order[0]);
        self::assertContains('headline', $order);
        self::assertContains('step-verified-detail', $order);
        self::assertSame(count($order), count(array_unique($order)), 'A node is announced once.');
    }

    private function headline(Composition $composition): string
    {
        $node = $composition->scene->findNode(new NodeId('headline'));

        return $node?->content ?? '';
    }
}
