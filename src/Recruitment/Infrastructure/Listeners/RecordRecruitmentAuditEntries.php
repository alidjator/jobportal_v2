<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Src\Recruitment\Domain\Events\ApplicantsExported;
use Src\Recruitment\Domain\Events\ApplicationHireUpdated;
use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Recruitment\Domain\Events\HiresExported;
use Src\Recruitment\Domain\Events\JobFillHandled;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;

/**
 * Writes the audit entries for the Recruitment context: the export of a job's applicants, the export of the hires of a period (the days, the job filter and the row count) and a withdrawal marked by the team (the application and the reason, never the note) and a change to the hire details (the old and the new join date, and whether the note changed, never the note, which may name the candidate and could not be erased from the audit) and "mark as filled" on a job (whether it was closed, the counts of rejected and skipped applications). Unlike the other audit listeners it runs in the request, not on a
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

    public function onHiresExported(HiresExported $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::EXPORT_HIRES, 'company', $event->companyId,
            ['from' => $event->from, 'to' => $event->to, 'job_id' => $event->jobId, 'row_count' => $event->rowCount, 'company_id' => $event->companyId],
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

    public function onHireUpdated(ApplicationHireUpdated $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->actorId, AuditAction::HIRE_UPDATED, 'application', $event->applicationId,
            ['old' => ['join_date' => $event->before?->joinDate->format('Y-m-d')], 'new' => ['join_date' => $event->after->joinDate->format('Y-m-d')], 'note_changed' => $event->after->note !== $event->before?->note, 'company_id' => $event->companyId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onJobFillHandled(JobFillHandled $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->actorId, AuditAction::JOB_FILLED, 'job', $event->jobId,
            ['closed' => $event->closed, 'rejected' => $event->rejected, 'skipped' => $event->skipped, 'company_id' => $event->companyId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
