<?php

namespace Src\Company\Application\Activity;

use DateTimeInterface;
use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The activity log of the company (the same filters as the list) as a CSV for its company admins. The export is audited before it is handed out, so when
 * the entry cannot be written nothing is sent. The rows are read slice by slice while the file is written, never collected.
 */
final readonly class ExportCompanyActivity
{
    private const int SLICE = 100;

    private const array HEADERS = ['Time (UTC)', 'Actor', 'Actor type', 'Action', 'Subject type', 'Subject ID', 'Subject', 'IP address', 'User agent'];

    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyActivityReader $reader,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /** @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended` */
    public function execute(Actor $actor, CompanyActivityFilter $filter, ?string $ipAddress, ?string $userAgent): CompanyActivityExport
    {
        $companyId = (int) $this->guard->company($actor)->id;
        $now = $this->clock->now();

        $this->audit->record(new AuditEntry(
            AuditActorType::User, $actor->id, AuditAction::COMPANY_ACTIVITY_EXPORTED, 'company', $companyId,
            array_filter(['user_id' => $filter->userId, 'group' => $filter->group, 'from' => $filter->from?->format(DateTimeInterface::ATOM), 'to' => $filter->to?->format(DateTimeInterface::ATOM)], static fn (mixed $value): bool => $value !== null),
            $ipAddress, $userAgent, $now, $companyId,
        ));

        return new CompanyActivityExport($companyId, $now, self::HEADERS, $this->rows($companyId, $filter));
    }

    /** @return iterable<list<string>> */
    private function rows(int $companyId, CompanyActivityFilter $filter): iterable
    {
        $cursor = null;

        do {
            $page = $this->reader->read($companyId, $filter, new CursorRequest($cursor, self::SLICE));

            foreach ($page->items as $row) {
                yield $this->cells($row);
            }

            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }

    /** @return list<string> */
    private function cells(CompanyActivityRow $row): array
    {
        $entry = $row->entry;
        $fromPlatform = $entry->actorType !== AuditActorType::User;

        return [
            $entry->occurredAt->format('Y-m-d\TH:i:s\Z'),
            $row->actorName ?? ($fromPlatform ? 'Platform' : ''),
            $entry->actorType->value,
            $entry->action,
            (string) $entry->subjectType,
            (string) $entry->subjectId,
            (string) $row->subjectTitle,
            $fromPlatform ? '' : (string) $entry->ipAddress,
            $fromPlatform ? '' : (string) $entry->userAgent,
        ];
    }
}
