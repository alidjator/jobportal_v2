<?php

namespace Src\Platform\Application\Audit;

use DateTimeImmutable;
use Src\Shared\Application\Audit\AuditActorType;

/** The filters of the audit log list; every one is optional and they combine with AND. `from` and `to` bound the time of the event, both inclusive. */
final readonly class AuditLogQuery
{
    public function __construct(
        public ?string $action = null,
        public ?AuditActorType $actorType = null,
        public ?int $actorId = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        /** @var ?list<string> only these actions */
        public ?array $actions = null,
        /** Only entries whose metadata `user_id` is this account (the member an entry concerns). */
        public ?int $metadataUserId = null,
        /** Only the entries of the company activity log of this company. */
        public ?int $companyId = null,
    ) {}
}
