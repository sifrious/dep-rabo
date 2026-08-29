<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Validation\Rule;

use Sifrious\Rabo\Composition\Node\TextNode;
use Sifrious\Rabo\Validation\IssueCode;
use Sifrious\Rabo\Validation\Rule;
use Sifrious\Rabo\Validation\ValidationContext;
use Sifrious\Rabo\Validation\ValidationIssue;

/**
 * The checks that keep a timeline honest.
 *
 * Three of these are ordinary correctness — cues inside the timeline, no unresolved fight
 * over one node, a reduced-motion strategy declared. The fourth is the one that matters most
 * and is easiest to miss: essential content must not exist only while an animation is
 * playing. Someone who pauses, arrives late, or has motion disabled must still get the point.
 */
final readonly class MotionRule implements Rule
{
    public function name(): string
    {
        return 'motion';
    }

    /** @return list<ValidationIssue> */
    public function check(ValidationContext $context): array
    {
        $motion = $context->motion;
        if ($motion === null) {
            return [];
        }

        $issues = [];
        $timeline = $motion->timeline;

        foreach ($timeline->cuesBeyondEnd() as $cue) {
            $issues[] = new ValidationIssue(
                IssueCode::MotionDurationInvalid,
                'motion.'.$cue->id,
                sprintf(
                    "Cue '%s' ends at %dms, past the %dms timeline.",
                    $cue->id, $cue->end()->milliseconds, $timeline->duration->milliseconds,
                ),
            );
        }

        foreach ($timeline->cues() as $cue) {
            if ($context->scene->findNode($cue->node) === null) {
                $issues[] = new ValidationIssue(
                    IssueCode::MotionDurationInvalid,
                    'motion.'.$cue->id,
                    "Cue '{$cue->id}' animates node '{$cue->node}', which scene '{$context->sceneName}' does not contain.",
                );
            }
        }

        foreach ($timeline->conflicts() as [$first, $second]) {
            $issues[] = new ValidationIssue(
                IssueCode::MotionCueOverlapUnresolved,
                'motion.'.$first->id,
                sprintf(
                    "Cues '%s' and '%s' both act on node '%s' between %dms and %dms with no stated winner.",
                    $first->id, $second->id, $first->node,
                    max($first->start->milliseconds, $second->start->milliseconds),
                    min($first->end()->milliseconds, $second->end()->milliseconds),
                ),
            );
        }

        if ($context->brand->accessibility->requireReducedMotionVariant) {
            $reduced = $motion->reducedMotionTimeline($context->brand);
            if ($reduced->duration->isZero()) {
                $issues[] = new ValidationIssue(
                    IssueCode::MotionReducedVariantRequired,
                    'motion.reduced_motion',
                    "Motion composition '{$motion->id}' produces no reduced-motion alternative.",
                );
            }
        }

        foreach ($context->scene->nodes() as $node) {
            if (! $node instanceof TextNode || ! $node->essential) {
                continue;
            }
            if (! $timeline->nodeVisibleAtEnd($node->id())) {
                $issues[] = new ValidationIssue(
                    IssueCode::MotionInformationOnlyTransient,
                    (string) $node->id(),
                    "Text '{$node->id()}' is essential but the timeline leaves it hidden at the end, so it exists only during playback.",
                );
            }
        }

        return $issues;
    }
}
