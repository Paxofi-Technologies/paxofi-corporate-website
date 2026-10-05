<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Mail\Email;

/**
 * Checks an application from careers.paxofi.com (D-019). Asks only for what
 * recruitment needs (PIF launch pack: no demographic, financial or other
 * sensitive data).
 */
final class ApplicationInput
{
    public const PRIVACY_VERSION = '2026-10-05';
    public const HONEYPOT_FIELD = 'website';
    public const HOURS = [15, 20, 25, 30, 35, 40];
    /** "How did you hear about this role?" (optional; P3.1). */
    public const SOURCES = [
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'referral' => 'A friend or colleague',
        'school' => 'University, school or bootcamp',
        'job_board' => 'A job board',
        'paxofi_website' => 'The Paxofi website',
        'search' => 'A search engine',
        'other' => 'Somewhere else',
    ];

    /** @return array<string, mixed> */
    public static function validate(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $errors = [];
        $line = static fn (string $key): string => is_string($input[$key] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', mb_scrub($input[$key], 'UTF-8')))) : '';
        $block = static fn (string $key): string => is_string($input[$key] ?? null) ? trim((string) preg_replace("/[^\\P{Cc}\n\t]/u", '', str_replace(["\r\n", "\r"], "\n", mb_scrub($input[$key], 'UTF-8')))) : '';

        $values = [
            'full_name' => $line('full_name'),
            'email' => strtolower($line('email')),
            'phone' => $line('phone'),
            'location' => $line('location'),
            'motivation' => $block('motivation'),
            'experience' => $block('experience'),
            'portfolio_url' => $line('portfolio_url'),
            'linkedin_url' => $line('linkedin_url'),
            'cv_token' => $line('cv_token'),
        ];
        if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 160) {
            $errors['full_name'] = 'Enter your full name.';
        }
        if (!Email::isAddress($values['email'])) {
            $errors['email'] = 'Enter a valid email address. We send every update there.';
        }
        if ($values['phone'] !== '' && preg_match('/^\+?[0-9 ()-]{7,20}$/', $values['phone']) !== 1) {
            $errors['phone'] = 'Enter a phone number with digits only, for example +234 801 234 5678, or leave it empty.';
        }
        if (mb_strlen($values['location']) < 2 || mb_strlen($values['location']) > 120) {
            $errors['location'] = 'Enter your country (and city if you like).';
        }
        $hours = $input['hours_per_week'] ?? null;
        $values['hours_per_week'] = is_numeric($hours) ? (int) $hours : 0;
        if (!in_array($values['hours_per_week'], self::HOURS, true)) {
            $errors['hours_per_week'] = 'Choose how many hours a week you can commit (at least 15).';
        }
        foreach (['motivation' => 'why you want this role', 'experience' => 'your relevant experience or evidence'] as $key => $what) {
            $length = mb_strlen($values[$key]);
            if ($length < 50 || $length > 3000) {
                $errors[$key] = "Tell us {$what} in 50 to 3,000 characters.";
            }
        }
        if ($values['portfolio_url'] !== '' && !self::isWebAddress($values['portfolio_url'])) {
            $errors['portfolio_url'] = 'Enter a full web address starting with https://, or leave it empty.';
        }
        if ($values['linkedin_url'] !== '' && (!self::isWebAddress($values['linkedin_url']) || preg_match('~^https?://([a-z0-9-]+\.)?linkedin\.com/~i', $values['linkedin_url']) !== 1)) {
            $errors['linkedin_url'] = 'Enter your LinkedIn profile address (https://www.linkedin.com/in/…), or leave it empty.';
        }
        if ($values['cv_token'] !== '' && preg_match('/^[A-Za-z0-9_-]{30,80}$/', $values['cv_token']) !== 1) {
            $errors['cv'] = 'Upload your CV again.';
        }
        if ($values['cv_token'] === '' && $values['portfolio_url'] === '' && $values['linkedin_url'] === '' && !isset($errors['cv'])) {
            $errors['cv'] = 'Add your CV, a portfolio link or your LinkedIn profile, so we can see your experience.';
        }
        if (($input['age_confirmed'] ?? false) !== true) {
            $errors['age_confirmed'] = 'Confirm that you are 18 or older.';
        }
        if (($input['privacy_consent'] ?? false) !== true) {
            $errors['privacy_consent'] = 'Please agree to how we use your application.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        $source = is_string($input['source'] ?? null) ? $input['source'] : '';
        $values['source'] = isset(self::SOURCES[$source]) ? $source : null;
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $tag) {
            $values[$tag] = self::campaignTag($input[$tag] ?? null);
        }
        foreach (['phone', 'portfolio_url', 'linkedin_url', 'cv_token'] as $optional) {
            $values[$optional] = $values[$optional] === '' ? null : $values[$optional];
        }
        $values['likely_spam'] = trim((string) ($input[self::HONEYPOT_FIELD] ?? '')) !== '';

        return $values;
    }

    /** A campaign tag from the link the candidate followed: lower-case letters, digits, "-", "_" and ".", up to 80; anything else is dropped. */
    public static function campaignTag(mixed $value): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return preg_match('/^[a-z0-9._-]{1,80}$/', $value) === 1 ? $value : null;
    }

    private static function isWebAddress(string $value): bool
    {
        return mb_strlen($value) <= 500 && preg_match('~^https?://[^\s"\'<>]+\.[^\s"\'<>]+$~i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
