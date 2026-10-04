<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

/** Checks a role edited in the staff area (D-018). Plain text only; lists are one item per line. */
final class RoleInput
{
    private const LISTS = ['responsibilities' => 12, 'deliverables' => 10, 'competencies' => 14, 'tools' => 14];

    /** @return array<string, mixed> */
    public static function validate(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $errors = [];
        $text = static function (string $key, int $min, int $max, string $message) use ($input, &$errors): string {
            $value = self::clean($input[$key] ?? '');
            if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
                $errors[$key] = $message;
            }

            return $value;
        };
        $values = [
            'title' => $text('title', 3, 80, 'Enter a role title of 3 to 80 characters.'),
            'code' => strtoupper($text('code', 2, 4, 'Enter a 2 to 4 letter code, for example SE.')),
            'family' => $text('family', 2, 80, 'Enter the role family (up to 80 characters).'),
            'summary' => $text('summary', 10, 300, 'Enter a summary of 10 to 300 characters.'),
            'purpose' => $text('purpose', 10, 600, 'Enter the purpose of the role (10 to 600 characters).'),
            'evidence' => $text('evidence', 0, 400, 'Keep this to 400 characters.'),
            'assessment' => $text('assessment', 0, 400, 'Keep this to 400 characters.'),
            'interview' => $text('interview', 0, 400, 'Keep this to 400 characters.'),
        ];
        if (!isset($errors['code']) && preg_match('/^[A-Z]{2,4}$/', $values['code']) !== 1) {
            $errors['code'] = 'Use 2 to 4 letters, for example SE.';
        }
        foreach (self::LISTS as $key => $limit) {
            $items = $input[$key] ?? [];
            $items = is_string($items) ? explode("\n", $items) : (is_array($items) ? $items : []);
            $items = array_values(array_filter(array_map(static fn (mixed $i): string => is_string($i) ? self::clean($i) : '', $items), static fn (string $i): bool => $i !== ''));
            if (count($items) > $limit) {
                $errors[$key] = "Keep this to {$limit} lines.";
            }
            foreach ($items as $item) {
                if (mb_strlen($item) > 160) {
                    $errors[$key] = 'Keep each line to 160 characters.';
                }
            }
            $values[$key] = $items;
        }
        if ($values['responsibilities'] === []) {
            $errors['responsibilities'] = 'Add at least one responsibility, one per line.';
        }
        $order = $input['sort_order'] ?? 100;
        $values['sort_order'] = is_numeric($order) ? max(0, min(9999, (int) $order)) : 100;
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        return $values;
    }

    public static function slug(string $title): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));

        return substr($slug, 0, 120) ?: 'role';
    }

    private static function clean(mixed $value): string
    {
        return is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', mb_scrub($value, 'UTF-8')))) : '';
    }
}
