<?php

namespace Src\Shared\Application\Audit;

use DateTimeImmutable;

/** One audit entry as another context reads it: who did what to which object, when and from where. */
final readonly class ActivityEntry
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
    ) {}
}
