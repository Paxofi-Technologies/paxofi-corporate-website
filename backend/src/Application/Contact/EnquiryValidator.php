<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Contact;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

/**
 * Validates and normalises raw contact form input into an EnquirySubmission.
 *
 * Limits mirror the `enquiries` column sizes and the frontend form attributes.
 */
final class EnquiryValidator
{
    public const MAX_NAME = 160;
    public const MAX_EMAIL = 255;
    public const MAX_COMPANY = 255;
    public const MAX_MESSAGE = 10000;

    /**
     * Hidden form field that humans never fill in. Bots that auto-complete
     * every input do, which lets us drop them without a CAPTCHA.
     */
    public const HONEYPOT_FIELD = 'website';

    /** @param array<string, mixed> $input */
    public function validate(array $input): EnquirySubmission
    {
        $errors = [];

        $name = $this->text($input, 'name', self::MAX_NAME, true, $errors);
        $email = $this->text($input, 'email', self::MAX_EMAIL, true, $errors);
        $company = $this->text($input, 'company', self::MAX_COMPANY, false, $errors);
        $message = $this->text($input, 'message', self::MAX_MESSAGE, true, $errors, multiline: true);

        if (!isset($errors['email']) && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        $honeypot = $input[self::HONEYPOT_FIELD] ?? '';

        return new EnquirySubmission(
            name: $name,
            email: strtolower($email),
            company: $company === '' ? null : $company,
            message: $message,
            isLikelySpam: !is_string($honeypot) || trim($honeypot) !== '',
        );
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    private function text(array $input, string $field, int $max, bool $required, array &$errors, bool $multiline = false): string
    {
        $raw = $input[$field] ?? '';
        if (!is_string($raw)) {
            $errors[$field] = 'Must be text.';

            return '';
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $errors[$field] = 'Contains invalid characters.';

            return '';
        }

        // Strip control characters (keep tab/newlines only for multi-line fields).
        $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        $value = trim((string) preg_replace($pattern, '', $raw));

        if ($required && $value === '') {
            $errors[$field] = 'This field is required.';
        } elseif (mb_strlen($value) > $max) {
            $errors[$field] = sprintf('Must be %d characters or fewer.', $max);
        }

        return $value;
    }
}
