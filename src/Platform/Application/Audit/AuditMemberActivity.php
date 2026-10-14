<?php

namespace Src\Platform\Application\Audit;

use DateTimeImmutable;
use Src\Shared\Application\Audit\ActivityEntry;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\MemberActivity;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;

/** Platform's side of `MemberActivity`: the same audit log admins read, filtered for the Company context. */
final readonly class AuditMemberActivity implements MemberActivity
{
    private const string COMPANY_SUBJECT_TYPE = 'company';

    public function __construct(private AuditLogReader $reader) {}

    public function ofMember(int $userId, ?string $action, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage
    {
        return $this->entries($this->reader->read(new AuditLogQuery(action: $action, actorType: AuditActorType::User, actorId: $userId, from: $from, to: $to), $request));
    }

    public function ofCompany(int $companyId, array $actions, ?int $memberUserId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, CursorRequest $request): CursorPage
    {
        return $this->entries($this->reader->read(new AuditLogQuery(
            subjectType: self::COMPANY_SUBJECT_TYPE, subjectId: $companyId, from: $from, to: $to, actions: $actions, metadataUserId: $memberUserId,
        ), $request));
    }

    /**
     * @param  CursorPage<AuditLogRecord>  $page
     * @return CursorPage<ActivityEntry>
     */
    private function entries(CursorPage $page): CursorPage
    {
        return new CursorPage(array_map(static fn (AuditLogRecord $r): ActivityEntry => new ActivityEntry(
            $r->id, $r->actorType, $r->actorId, $r->action, $r->subjectType, $r->subjectId, $r->metadata, $r->ipAddress, $r->userAgent, $r->occurredAt,
        ), $page->items), $page->nextCursor, $page->perPage);
    }
}
