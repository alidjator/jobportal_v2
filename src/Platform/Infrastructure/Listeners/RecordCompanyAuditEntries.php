<?php

namespace Src\Platform\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Domain\Events\CompanyCreated;
use Src\Company\Domain\Events\CompanyMemberJoined;
use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Company context: an admin creating a company and a person joining one (a membership change).
 * Runs on the `default` queue, retried 3 times with a growing delay. The entries never hold an email address.
 */
final class RecordCompanyAuditEntries implements ShouldQueue
{
    private const string SUBJECT_TYPE = 'company';

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

    public function onCompanyCreated(CompanyCreated $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_CREATED,
            self::SUBJECT_TYPE, $event->companyId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberJoined(CompanyMemberJoined $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::COMPANY_MEMBER_JOINED,
            self::SUBJECT_TYPE, $event->companyId, ['role' => $event->role], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
