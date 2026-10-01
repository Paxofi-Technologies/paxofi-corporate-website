<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Support;

use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;

final class RecordingAuditRecorder implements AuditRecorder
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function record(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}
