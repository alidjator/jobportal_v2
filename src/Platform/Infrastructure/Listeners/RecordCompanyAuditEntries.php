<?php

namespace Src\Platform\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Domain\Events\CompanyCreated;
use Src\Company\Domain\Events\CompanyInvitationRevoked;
use Src\Company\Domain\Events\CompanyInvitationSent;
use Src\Company\Domain\Events\CompanyMemberJoined;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Company\Domain\Events\CompanyMemberRoleChanged;
use Src\Company\Domain\Events\CompanyRestored;
use Src\Company\Domain\Events\CompanySuspended;
use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Company context: an admin creating a company, the invitations sent and revoked (they grant access to a
 * company's data) and the membership changes (joining, removal, role change).
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

    public function onCompanySuspended(CompanySuspended $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_SUSPENDED,
            self::SUBJECT_TYPE, $event->companyId, ['reason' => $event->reason], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyRestored(CompanyRestored $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_RESTORED,
            self::SUBJECT_TYPE, $event->companyId, $event->note === null ? [] : ['note' => $event->note],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyInvitationSent(CompanyInvitationSent $event): void
    {
        $byAdmin = $event->invitedByAdminId !== null;

        $this->record->execute(new AuditEntry(
            $byAdmin ? AuditActorType::Admin : AuditActorType::User, $byAdmin ? $event->invitedByAdminId : $event->invitedByUserId,
            AuditAction::COMPANY_INVITATION_SENT, self::SUBJECT_TYPE, $event->companyId,
            ['invitation_id' => $event->invitationId, 'role' => $event->role], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyInvitationRevoked(CompanyInvitationRevoked $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::User, $event->revokedByUserId, AuditAction::COMPANY_INVITATION_REVOKED,
            self::SUBJECT_TYPE, $event->companyId, ['invitation_id' => $event->invitationId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberRemoved(CompanyMemberRemoved $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::User, $event->removedByUserId, AuditAction::COMPANY_MEMBER_REMOVED,
            self::SUBJECT_TYPE, $event->companyId, ['user_id' => $event->userId, 'role' => $event->role],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberRoleChanged(CompanyMemberRoleChanged $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::User, $event->changedByUserId, AuditAction::COMPANY_MEMBER_ROLE_CHANGED,
            self::SUBJECT_TYPE, $event->companyId, ['user_id' => $event->userId, 'old_role' => $event->oldRole, 'new_role' => $event->newRole],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
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
