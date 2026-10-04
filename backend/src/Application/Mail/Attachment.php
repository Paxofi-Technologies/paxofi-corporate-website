<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

/** A file sent with an email (D-019): the name is reduced to safe characters. */
final readonly class Attachment
{
    public string $filename;

    public function __construct(string $filename, public string $mediaType, public string $content)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $base = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '', pathinfo($filename, PATHINFO_FILENAME))) ?: 'attachment';
        $this->filename = mb_substr($base, 0, 120) . ($extension !== '' && preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1 ? '.' . $extension : '');
    }
}
