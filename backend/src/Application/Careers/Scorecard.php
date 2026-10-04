<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

/**
 * The two scorecards of the PIF 2026 HR screening package (D-019): Gate 2
 * evidence review (progress at 21/35) and Gate 4 interview (24/35). Each
 * criterion is scored 1 to 5.
 */
final class Scorecard
{
    public const GATES = [
        'evidence' => [
            'label' => 'Gate 2: evidence review',
            'pass' => 21,
            'criteria' => [
                'capability' => 'Role capability',
                'evidence' => 'Evidence or portfolio',
                'communication' => 'Communication',
                'reliability' => 'Reliability and ownership',
                'learning' => 'Learning agility',
                'collaboration' => 'Collaboration',
                'readiness' => 'Role-specific readiness',
            ],
        ],
        'interview' => [
            'label' => 'Gate 4: interview',
            'pass' => 24,
            'criteria' => [
                'motivation' => 'Motivation and programme fit',
                'competence' => 'Role competence',
                'problem_solving' => 'Problem solving',
                'communication' => 'Communication',
                'collaboration' => 'Collaboration',
                'accountability' => 'Accountability',
                'learning' => 'Learning orientation',
            ],
        ],
    ];

    /** @return array<string, int> */
    public static function validate(string $gate, mixed $scores): array
    {
        if (!isset(self::GATES[$gate])) {
            throw new ValidationFailed(['gate' => 'Choose the evidence review or the interview scorecard.'], 'Please correct the highlighted fields.');
        }
        $scores = is_array($scores) ? $scores : [];
        $clean = [];
        $errors = [];
        foreach (self::GATES[$gate]['criteria'] as $key => $label) {
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

    /** @param array<string, int>|null $scores */
    public static function summary(string $gate, ?array $scores): ?array
    {
        if ($scores === null) {
            return null;
        }
        $total = array_sum($scores);

        return ['scores' => $scores, 'total' => $total, 'out_of' => 35, 'pass' => self::GATES[$gate]['pass'], 'passed' => $total >= self::GATES[$gate]['pass']];
    }

    /** @return array<string, array{label: string, pass: int, criteria: array<string, string>}> */
    public static function definitions(): array
    {
        return self::GATES;
    }
}
