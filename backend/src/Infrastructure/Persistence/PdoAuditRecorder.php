<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Persistence\Exception\PersistenceException;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

final class PdoAuditRecorder implements AuditRecorder
{
    public function __construct(private readonly Database $database)
    {
    }

    public function record(AuditEvent $event): void
    {
        try {
            $this->database->writer()->execute(
                'INSERT INTO audit_events (id, actor_id, action, target_type, target_id, outcome, request_id)
                 VALUES (:id, :actor_id, :action, :target_type, :target_id, :outcome, :request_id)',
                [
                    'id' => Uuid::v4(),
                    'actor_id' => $event->actorId,
                    'action' => $event->action,
                    'target_type' => $event->targetType,
                    'target_id' => $event->targetId,
                    'outcome' => $event->outcome,
                    'request_id' => $event->requestId,
                ],
            );
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }
    }
}
