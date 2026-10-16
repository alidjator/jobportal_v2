<?php

namespace Src\Platform\Application\Audit;

use DateTimeImmutable;
use Src\Shared\Application\Audit\AuditActorType;

/** One stored audit entry as read back, with its id. */
final readonly class AuditLogRecord
{
    /** @param  array<string, mixed>  $metadata */
    public function __construct(
        public int $id,
        public AuditActorType $actorType,
        public ?int $actorId,
        public string $action,
        public ?string $subjectType,
        public ?int $subjectId,
        public array $metadata,
        public ?string $ipAddress,
        public ?string $userAgent,
        public DateTimeImmutable $occurredAt,
        public ?int $companyId = null,
        public ?int $groupId = null,
    ) {}
}
