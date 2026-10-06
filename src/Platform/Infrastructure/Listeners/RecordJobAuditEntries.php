<?php

namespace Src\Platform\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
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

    public function __construct(private readonly RecordAuditEntry $record) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onJobAssigneesChanged(JobAssigneesChanged $event): void
    {
        $this->record->execute(new AuditEntry(
            $event->changedByUserId === null ? AuditActorType::System : AuditActorType::User, $event->changedByUserId, AuditAction::JOB_ASSIGNEES_CHANGED,
            self::SUBJECT_TYPE, $event->jobId, ['added' => $event->added, 'removed' => $event->removed],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
