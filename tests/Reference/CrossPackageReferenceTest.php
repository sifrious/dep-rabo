<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Reference;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sifrious\Rabo\Portable\CompositionBundle;
use Sifrious\Rabo\Reference\CompositionReferences;
use Sifrious\Rabo\Reference\ReferenceRole;
use Sifrious\Rabo\Tests\Fixture;
use Sifrious\ReferenceContract\CrossPackageReference;

final class CrossPackageReferenceTest extends TestCase
{
    public function test_references_use_the_shared_cross_package_contract(): void
    {
        $references = CompositionBundle::load(Fixture::path())->composition->references;
        $treatment = $references->one(ReferenceRole::Treatment);

        self::assertInstanceOf(CrossPackageReference::class, $treatment);
        self::assertSame('sifrious.cross-package-reference', $treatment->toArray()['contract']);
        self::assertSame(1, $treatment->toArray()['contract_version']);
    }

    public function test_the_composition_can_say_what_caused_it_and_what_supports_it(): void
    {
        $references = CompositionBundle::load(Fixture::path())->composition->references;

        self::assertSame('treatment_agent_completion_v1', $references->one(ReferenceRole::Treatment)?->id);
        self::assertSame('storyboard_five_beat', $references->one(ReferenceRole::Storyboard)?->id);

        $evidence = array_map(static fn (CrossPackageReference $r): string => $r->owner.'/'.$r->id, $references->all(ReferenceRole::Evidence));
        self::assertContains('sifrious/digory/handoff_instructions_required_agent_completion_report', $evidence);
        self::assertContains('sifrious/funes/linear_rabo_tickets_in_review_without_attachments', $evidence);
    }

    public function test_missing_optional_references_are_silent_and_missing_required_ones_are_not(): void
    {
        $references = CompositionBundle::load(Fixture::path())->composition->references;

        self::assertFalse($references->has(ReferenceRole::Review), 'Review has not happened yet.');
        self::assertFalse($references->has(ReferenceRole::Delivery), 'Delivery has not happened yet.');
        self::assertNull($references->one(ReferenceRole::Review));
        self::assertSame([], $references->missingRequired(), 'Treatment and evidence are present.');
    }

    public function test_a_reference_may_not_be_owned_by_the_wrong_package(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("may not be owned by 'sifrious/rabo'");

        (new CompositionReferences())->with(ReferenceRole::Review, new CrossPackageReference('sifrious/rabo', 'review', 'r1'));
    }

    public function test_only_evidence_accepts_more_than_one_reference(): void
    {
        $first = new CrossPackageReference('sifrious/orual', 'review', 'r1');
        $second = new CrossPackageReference('sifrious/orual', 'review', 'r2');

        $replaced = (new CompositionReferences())
            ->with(ReferenceRole::Review, $first)
            ->with(ReferenceRole::Review, $second);

        self::assertCount(1, $replaced->all(ReferenceRole::Review), 'A single-valued role is replaced, not appended.');
        self::assertSame('r2', $replaced->one(ReferenceRole::Review)?->id);

        $accumulated = (new CompositionReferences())
            ->with(ReferenceRole::Evidence, new CrossPackageReference('sifrious/digory', 'document', 'd1'))
            ->with(ReferenceRole::Evidence, new CrossPackageReference('sifrious/funes', 'observation', 'o1'));

        self::assertCount(2, $accumulated->all(ReferenceRole::Evidence));
    }

    public function test_a_composition_with_no_evidence_names_exactly_what_is_missing(): void
    {
        $missing = (new CompositionReferences())->missingRequired();

        self::assertSame(
            [ReferenceRole::Treatment, ReferenceRole::Evidence],
            $missing,
        );
    }

    public function test_downstream_roles_can_be_attached_later_without_rewriting_the_composition(): void
    {
        $bundle = CompositionBundle::load(Fixture::path());
        $before = $bundle->composition->references;

        $after = $before
            ->with(ReferenceRole::Review, new CrossPackageReference('sifrious/orual', 'review', 'approved-2026-08-29'))
            ->with(ReferenceRole::Delivery, new CrossPackageReference('sifrious/trout', 'delivery', 'linkedin-2026-09-01'));

        self::assertFalse($before->has(ReferenceRole::Review), 'The original envelope is unchanged.');
        self::assertTrue($after->has(ReferenceRole::Review));
        self::assertTrue($after->has(ReferenceRole::Delivery));
        self::assertSame('approved-2026-08-29', $after->one(ReferenceRole::Review)?->id);
    }

    public function test_references_round_trip_through_their_wire_form(): void
    {
        $references = CompositionBundle::load(Fixture::path())->composition->references;
        $restored = CompositionReferences::fromArray(json_decode($references->canonical(), true, flags: JSON_THROW_ON_ERROR));

        self::assertTrue($restored->equals($references));
        self::assertSame($references->canonical(), $restored->canonical());
    }

    public function test_rabo_holds_references_without_holding_the_objects(): void
    {
        $reference = CompositionBundle::load(Fixture::path())->composition->references->one(ReferenceRole::Treatment);

        self::assertNotNull($reference);
        self::assertSame(['contract', 'contract_version', 'owner', 'type', 'id', 'object_version', 'provenance'], array_keys($reference->toArray()),
            'A reference names an object; it never carries its contents.');
    }
}
