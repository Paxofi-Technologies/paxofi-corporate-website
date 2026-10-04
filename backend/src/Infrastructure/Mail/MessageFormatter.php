<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Mail;

use Paxofi\CorporateWebsite\Application\Mail\Email;

/**
 * RFC 5322 text message: encoded headers, base64 UTF-8 body, CRLF line
 * endings. With attachments it is multipart/mixed (RFC 2046), each file base64.
 */
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
            'Auto-Submitted: auto-generated',
        ];
        if ($email->replyTo !== null) {
            $headers[] = 'Reply-To: ' . $email->replyTo;
        }
        // base64 lines never start with ".", so no dot-stuffing is needed.
        $text = self::base64(str_replace(["\r\n", "\r"], "\n", $email->text));
        if ($email->attachments === []) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';

            return implode("\r\n", $headers) . "\r\n\r\n" . $text;
        }

        $boundary = '=_paxofi_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $parts = ["Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $text];
        foreach ($email->attachments as $attachment) {
            $type = preg_match('#^[a-z]+/[a-z0-9.+-]+$#', $attachment->mediaType) === 1 ? $attachment->mediaType : 'application/octet-stream';
            $parts[] = "Content-Type: {$type}; name=\"{$attachment->filename}\"\r\n"
                . "Content-Disposition: attachment; filename=\"{$attachment->filename}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n" . self::base64($attachment->content);
        }
        $body = '--' . $boundary . "\r\n" . implode("\r\n--" . $boundary . "\r\n", $parts) . "\r\n--" . $boundary . '--';

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private static function base64(string $bytes): string
    {
        return rtrim(chunk_split(base64_encode($bytes), 76, "\r\n"));
    }

    private static function encode(string $value): string
    {
        $value = Email::oneLine($value);

        return preg_match('/^[\x20-\x7E]*$/', $value) === 1 && !str_contains($value, '=?') ? $value : '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
