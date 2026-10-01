<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Audit;

/**
 * A security/business-relevant event. Never place personal data (names,
 * emails, message bodies) in an audit event; reference records by id.
 */
final readonly class AuditEvent
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_DENIED = 'denied';
    public const OUTCOME_FAILURE = 'failure';

    public function __construct(
        public string $action,
        public string $outcome,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $actorId = null,
        public ?string $requestId = null,
    ) {
    }
}
