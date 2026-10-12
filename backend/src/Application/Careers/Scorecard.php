<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

/**
 * The scorecards of the PIF 2026 selection package (D-019, D-027; Launch
 * Production Pack section 8). Each criterion is scored 1 to 5.
 *
 * - Evidence review (Fellows and Interns): 7 criteria, 35 points. Progress at 21 (Fellows) or 18 (Interns).
 * - Interview (Fellows and Interns): 7 criteria, 35 points. Select at 24 (Fellows) or 21 (Interns).
 * - Short conversation (Volunteers): 5 criteria, 25 points. Select at 15. Volunteers have no evidence review.
 */
final class Scorecard
{
    private const EVIDENCE_CRITERIA = [
        'capability' => 'Role capability',
        'evidence' => 'Evidence or portfolio',
        'communication' => 'Communication',
        'reliability' => 'Reliability and ownership',
        'learning' => 'Learning agility',
        'collaboration' => 'Collaboration',
        'readiness' => 'Role-specific readiness',
    ];

    private const INTERVIEW_CRITERIA = [
        'motivation' => 'Motivation and programme fit',
        'competence' => 'Role competence',
        'problem_solving' => 'Problem solving',
        'communication' => 'Communication',
        'collaboration' => 'Collaboration',
        'accountability' => 'Accountability',
        'learning' => 'Learning orientation',
    ];

    private const CONVERSATION_CRITERIA = [
        'motivation' => 'Motivation',
        'communication' => 'Communication',
        'reliability' => 'Reliability',
        'collaboration' => 'Collaboration',
        'availability' => 'Availability',
    ];

    /** @return array<string, array{label: string, pass: int, out_of: int, criteria: array<string, string>}> the scorecards that apply to the engagement type, by gate */
    public static function definitions(EngagementType $type): array
    {
        $gates = [];
        if ($type->hasEvidenceGate()) {
            $gates['evidence'] = ['label' => 'Gate 2: evidence review', 'pass' => (int) $type->evidencePass(), 'out_of' => 35, 'criteria' => self::EVIDENCE_CRITERIA];
        }
        $gates['interview'] = $type === EngagementType::Volunteer
            ? ['label' => 'Gate 4: short conversation', 'pass' => $type->interviewPass(), 'out_of' => $type->interviewOutOf(), 'criteria' => self::CONVERSATION_CRITERIA]
            : ['label' => 'Gate 4: interview', 'pass' => $type->interviewPass(), 'out_of' => $type->interviewOutOf(), 'criteria' => self::INTERVIEW_CRITERIA];

        return $gates;
    }

    /** @return array<string, int> */
    public static function validate(EngagementType $type, string $gate, mixed $scores): array
    {
        $definitions = self::definitions($type);
        if (!isset($definitions[$gate])) {
            throw new ValidationFailed(['gate' => $type->hasEvidenceGate() ? 'Choose the evidence review or the interview scorecard.' : 'Volunteers have a short conversation scorecard only.'], 'Please correct the highlighted fields.');
        }
        $scores = is_array($scores) ? $scores : [];
        $clean = [];
        $errors = [];
        foreach ($definitions[$gate]['criteria'] as $key => $label) {
            $value = $scores[$key] ?? null;
            if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
                $errors[$key] = "Score {$label} from 1 to 5.";
                continue;
            }
            $value = (int) $value;
            if ($value < 1 || $value > 5) {
                $errors[$key] = "Score {$label} from 1 to 5.";
                continue;
            }
            $clean[$key] = $value;
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please score every criterion from 1 to 5.');
        }

        return $clean;
    }

    /**
     * @param array<string, int>|null $scores
     * @return array{scores: array<string, int>, total: int, out_of: int, pass: int, passed: bool}|null
     */
    public static function summary(EngagementType $type, string $gate, ?array $scores): ?array
    {
        $definition = self::definitions($type)[$gate] ?? null;
        if ($scores === null || $definition === null) {
            return null;
        }
        $total = array_sum($scores);

        return ['scores' => $scores, 'total' => $total, 'out_of' => $definition['out_of'], 'pass' => $definition['pass'], 'passed' => $total >= $definition['pass']];
    }
}
