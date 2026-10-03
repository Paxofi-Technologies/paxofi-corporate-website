<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** Shared validation of staff account fields. */
final class StaffInput
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    public static function email(array $input, array &$errors, string $field = 'email'): string
    {
        $email = is_string($input[$field] ?? null) ? strtolower(trim($input[$field])) : '';
        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[$field] = 'Enter a valid email address.';
        }

        return $email;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    public static function displayName(array $input, array &$errors, bool $required = true): ?string
    {
        if (!array_key_exists('display_name', $input) && !$required) {
            return null;
        }
        $name = is_string($input['display_name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $input['display_name']) ?? '') : '';
        if ($name === '' || mb_strlen($name) > 160) {
            $errors['display_name'] = 'Enter a name of up to 160 characters.';
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    public static function password(array $input, string $email, array &$errors, string $field = 'password'): string
    {
        $password = is_string($input[$field] ?? null) ? $input[$field] : '';
        $problem = PasswordPolicy::problem($password, $email);
        if ($problem !== null) {
            $errors[$field] = $problem;
        }

        return $password;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     */
    public static function role(array $input, array &$errors, bool $required = true): ?Role
    {
        if (!array_key_exists('role', $input) && !$required) {
            return null;
        }
        $role = is_string($input['role'] ?? null) ? Role::tryFrom($input['role']) : null;
        if ($role === null) {
            $errors['role'] = 'Choose a role.';
        }

        return $role;
    }
}
