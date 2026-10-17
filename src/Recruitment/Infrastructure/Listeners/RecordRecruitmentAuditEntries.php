<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Src\Recruitment\Domain\Events\ApplicantsExported;
use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;

/**
 * Writes the audit entries for the Recruitment context: the export of a job's applicants and a withdrawal marked by the team (the application and the reason, never the note). Unlike the other audit listeners it runs in the request, not on a
 * queue, because an export that cannot be audited must not be sent. The entry holds the filters and the row count, never a name, an email or the search text.
 */
final readonly class RecordRecruitmentAuditEntries
{
    public function __construct(private AuditTrail $trail) {}

    public function onApplicantsExported(ApplicantsExported $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::EXPORT_APPLICANTS, 'job', $event->jobId,
            ['filters' => $event->filters, 'row_count' => $event->rowCount, 'company_id' => $event->companyId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onApplicationWithdrawn(ApplicationWithdrawn $event): void
    {
        if (! $event->byTeam() || $event->actorId === null) {
            return;
        }

        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->actorId, AuditAction::APPLICATION_WITHDRAWN_BY_COMPANY, 'application', $event->applicationId,
            ['reason' => $event->reason?->value, 'company_id' => $event->companyId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
