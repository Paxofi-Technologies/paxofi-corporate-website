<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Audit;

interface AuditRecorder
{
    public function record(AuditEvent $event): void;
}
