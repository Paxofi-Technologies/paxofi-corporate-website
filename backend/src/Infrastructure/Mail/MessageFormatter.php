<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Mail;

use Paxofi\CorporateWebsite\Application\Mail\Email;

/** RFC 5322 text message: encoded headers, base64 UTF-8 body, CRLF line endings. */
final class MessageFormatter
{
    public static function format(Email $email, string $fromAddress, string $fromName): string
    {
        $domain = substr(strrchr($fromAddress, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . self::encode($fromName) . ' <' . $fromAddress . '>',
            'To: ' . implode(', ', $email->to),
            'Subject: ' . self::encode($email->subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',
        ];
        if ($email->replyTo !== null) {
            $headers[] = 'Reply-To: ' . $email->replyTo;
        }
        // base64 lines never start with ".", so no dot-stuffing is needed.
        $body = rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $email->text)), 76, "\r\n"));

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private static function encode(string $value): string
    {
        $value = Email::oneLine($value);

        return preg_match('/^[\x20-\x7E]*$/', $value) === 1 && !str_contains($value, '=?') ? $value : '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
