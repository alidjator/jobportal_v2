<?php

namespace Src\Platform\Infrastructure\Listeners;

use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Recruitment\Domain\Events\ApplicantsExported;

/**
 * Writes the audit entries for the Recruitment context: the export of a job's applicants. Unlike the other audit listeners it runs in the request, not on a
 * queue, because an export that cannot be audited must not be sent. The entry holds the filters and the row count, never a name, an email or the search text.
 */
final readonly class RecordRecruitmentAuditEntries
{
    public function __construct(private RecordAuditEntry $record) {}

    public function onApplicantsExported(ApplicantsExported $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::EXPORT_APPLICANTS, 'job', $event->jobId,
            ['filters' => $event->filters, 'row_count' => $event->rowCount, 'company_id' => $event->companyId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
