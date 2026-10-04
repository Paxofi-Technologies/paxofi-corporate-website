<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/** Careers site and recruitment email settings (D-018, D-019). */
final readonly class RecruitmentSettings
{
    /** @param list<string> $alertTo */
    public function __construct(
        public array $alertTo = [],
        public string $replyTo = 'hr@paxofi.com',
        public string $careersSiteUrl = 'https://careers.paxofi.com',
    ) {
    }
}
