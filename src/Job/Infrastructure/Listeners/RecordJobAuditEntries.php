<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Events\JobReportsClosed;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Job context. A change of assignees is audited because an assignment gives access to the applications of a job; the
 * actor is the company admin, or the system when a removed member's jobs were handed to the company admins. The entries hold account ids, never names or emails.
 * Runs on the `default` queue, retried 3 times with a growing delay.
 */
final class RecordJobAuditEntries implements ShouldQueue
{
    private const string SUBJECT_TYPE = 'job';

    public int $tries = 3;

    public function __construct(private readonly AuditTrail $trail) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onJobStatusChanged(JobStatusChanged $event): void
    {
        $action = match ($event->change) {
            JobStatusChanged::PUBLISHED => AuditAction::JOB_PUBLISHED,
            JobStatusChanged::CLOSED => AuditAction::JOB_CLOSED,
            JobStatusChanged::REOPENED => AuditAction::JOB_REOPENED,
            JobStatusChanged::SENT_FOR_REVIEW => AuditAction::JOB_SENT_FOR_REVIEW,
            JobStatusChanged::EXPIRED => AuditAction::JOB_EXPIRED,
            default => throw new \LogicException("Unknown job status change {$event->change}."),
        };

        $this->trail->record(new AuditEntry(
            $event->changedByUserId === null ? AuditActorType::System : AuditActorType::User, $event->changedByUserId, $action,
            self::SUBJECT_TYPE, $event->jobId, ['from' => $event->from, 'to' => $event->to], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onJobDecided(JobDecided $event): void
    {
        $action = match ($event->decision) {
            JobDecided::APPROVED => AuditAction::JOB_APPROVED,
            JobDecided::REJECTED => AuditAction::JOB_REJECTED,
            JobDecided::TAKEN_DOWN => AuditAction::JOB_TAKEN_DOWN,
            JobDecided::RESTORED => AuditAction::JOB_RESTORED,
            default => throw new \LogicException("Unknown job decision {$event->decision}."),
        };

        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, $action, self::SUBJECT_TYPE, $event->jobId,
            ['from' => $event->from, 'to' => $event->to, ...($event->reason === null ? [] : ['reason' => $event->reason])],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    /** A dismissal is audited; a lifted hold is audited whichever way it ended (a take-down is audited by `onJobDecided`). */
    public function onJobReportsClosed(JobReportsClosed $event): void
    {
        if ($event->outcome === JobReportsClosed::DISMISSED) {
            $this->trail->record(new AuditEntry(
                AuditActorType::Admin, $event->adminId, AuditAction::JOB_REPORTS_DISMISSED, self::SUBJECT_TYPE, $event->jobId, ['closed' => $event->closed],
                $event->ipAddress, $event->userAgent, $event->occurredAt(),
            ));
        }

        if ($event->holdReleased) {
            $this->trail->record(new AuditEntry(
                AuditActorType::Admin, $event->adminId, AuditAction::JOB_HOLD_RELEASED, self::SUBJECT_TYPE, $event->jobId, ['outcome' => $event->outcome],
                $event->ipAddress, $event->userAgent, $event->occurredAt(),
            ));
        }
    }

    public function onJobAssigneesChanged(JobAssigneesChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            $event->changedByUserId === null ? AuditActorType::System : AuditActorType::User, $event->changedByUserId, AuditAction::JOB_ASSIGNEES_CHANGED,
            self::SUBJECT_TYPE, $event->jobId, ['added' => $event->added, 'removed' => $event->removed, ...($event->rolesChanged === [] ? [] : ['roles_changed' => $event->rolesChanged])],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
