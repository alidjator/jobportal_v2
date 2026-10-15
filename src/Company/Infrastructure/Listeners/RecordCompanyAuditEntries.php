<?php

namespace Src\Company\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Domain\Events\CompanyCreated;
use Src\Company\Domain\Events\CompanyDocumentApproved;
use Src\Company\Domain\Events\CompanyDocumentDeleted;
use Src\Company\Domain\Events\CompanyDocumentRejected;
use Src\Company\Domain\Events\CompanyDocumentSubmitted;
use Src\Company\Domain\Events\CompanyDocumentViewed;
use Src\Company\Domain\Events\CompanyEmailDomainsChanged;
use Src\Company\Domain\Events\CompanyInvitationResent;
use Src\Company\Domain\Events\CompanyInvitationRevoked;
use Src\Company\Domain\Events\CompanyInvitationSent;
use Src\Company\Domain\Events\CompanyInvitationUpdated;
use Src\Company\Domain\Events\CompanyMemberJoined;
use Src\Company\Domain\Events\CompanyMemberPermissionsChanged;
use Src\Company\Domain\Events\CompanyMemberReactivated;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Company\Domain\Events\CompanyMemberRenamed;
use Src\Company\Domain\Events\CompanyMemberRoleChanged;
use Src\Company\Domain\Events\CompanyMemberSuspended;
use Src\Company\Domain\Events\CompanyMemberTwoFactorReset;
use Src\Company\Domain\Events\CompanyOwnerTransferred;
use Src\Company\Domain\Events\CompanyRestored;
use Src\Company\Domain\Events\CompanySuspended;
use Src\Company\Domain\Events\DocumentTypeCreated;
use Src\Company\Domain\Events\DocumentTypeVerificationRulesChanged;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Company context: an admin creating a company, the invitations sent and revoked (they grant access to a
 * company's data), the membership changes (joining, removal, role and permission changes) and the document types that affect verification.
 * Runs on the `default` queue, retried 3 times with a growing delay. The entries never hold an email address.
 */
final class RecordCompanyAuditEntries implements ShouldQueue
{
    private const string SUBJECT_TYPE = 'company';

    private const string DOCUMENT_TYPE_SUBJECT_TYPE = 'company_document_type';

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

    public function onCompanyCreated(CompanyCreated $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_CREATED,
            self::SUBJECT_TYPE, $event->companyId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanySuspended(CompanySuspended $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_SUSPENDED,
            self::SUBJECT_TYPE, $event->companyId, ['reason' => $event->reason], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyRestored(CompanyRestored $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_RESTORED,
            self::SUBJECT_TYPE, $event->companyId, $event->note === null ? [] : ['note' => $event->note],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyInvitationSent(CompanyInvitationSent $event): void
    {
        $byAdmin = $event->invitedByAdminId !== null;

        $this->trail->record(new AuditEntry(
            $byAdmin ? AuditActorType::Admin : AuditActorType::User, $byAdmin ? $event->invitedByAdminId : $event->invitedByUserId,
            AuditAction::COMPANY_INVITATION_SENT, self::SUBJECT_TYPE, $event->companyId,
            ['invitation_id' => $event->invitationId, 'role' => $event->role, 'permissions' => $event->permissions], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyInvitationRevoked(CompanyInvitationRevoked $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->revokedByUserId, AuditAction::COMPANY_INVITATION_REVOKED,
            self::SUBJECT_TYPE, $event->companyId, ['invitation_id' => $event->invitationId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberRemoved(CompanyMemberRemoved $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->removedByUserId, AuditAction::COMPANY_MEMBER_REMOVED,
            self::SUBJECT_TYPE, $event->companyId, ['user_id' => $event->userId, 'role' => $event->role, 'transfer_to_user_id' => $event->transferToUserId],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberRoleChanged(CompanyMemberRoleChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->changedByUserId, AuditAction::COMPANY_MEMBER_ROLE_CHANGED,
            self::SUBJECT_TYPE, $event->companyId, [
                'user_id' => $event->userId, 'old_role' => $event->oldRole, 'new_role' => $event->newRole, 'permissions' => $event->newPermissions,
            ],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberPermissionsChanged(CompanyMemberPermissionsChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->changedByUserId, AuditAction::COMPANY_MEMBER_PERMISSIONS_CHANGED, self::SUBJECT_TYPE, $event->companyId,
            [
                'user_id' => $event->userId,
                'before' => $event->before,
                'added' => array_values(array_diff($event->after, $event->before)),
                'removed' => array_values(array_diff($event->before, $event->after)),
            ],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberRenamed(CompanyMemberRenamed $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->changedByUserId, AuditAction::COMPANY_MEMBER_RENAMED, self::SUBJECT_TYPE, $event->companyId,
            ['user_id' => $event->userId, 'old_name' => $event->oldName, 'new_name' => $event->newName], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberSuspended(CompanyMemberSuspended $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->suspendedByUserId, AuditAction::COMPANY_MEMBER_SUSPENDED, self::SUBJECT_TYPE, $event->companyId,
            ['user_id' => $event->userId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberTwoFactorReset(CompanyMemberTwoFactorReset $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->resetByUserId, AuditAction::COMPANY_MEMBER_TWO_FACTOR_RESET, self::SUBJECT_TYPE, $event->companyId,
            ['user_id' => $event->userId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberReactivated(CompanyMemberReactivated $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->reactivatedByUserId, AuditAction::COMPANY_MEMBER_REACTIVATED, self::SUBJECT_TYPE, $event->companyId,
            ['user_id' => $event->userId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyOwnerTransferred(CompanyOwnerTransferred $event): void
    {
        $byAdmin = $event->byAdminId !== null;

        $this->trail->record(new AuditEntry(
            $byAdmin ? AuditActorType::Admin : AuditActorType::User, $byAdmin ? $event->byAdminId : $event->fromUserId, AuditAction::COMPANY_OWNER_TRANSFERRED,
            self::SUBJECT_TYPE, $event->companyId, ['user_id' => $event->toUserId, 'from_user_id' => $event->fromUserId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyInvitationUpdated(CompanyInvitationUpdated $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->updatedByUserId, AuditAction::COMPANY_INVITATION_UPDATED, self::SUBJECT_TYPE, $event->companyId,
            ['invitation_id' => $event->invitationId, 'old_role' => $event->oldRole, 'role' => $event->role, 'permissions' => $event->permissions],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyInvitationResent(CompanyInvitationResent $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->resentByUserId, AuditAction::COMPANY_INVITATION_RESENT, self::SUBJECT_TYPE, $event->companyId,
            ['invitation_id' => $event->invitationId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyEmailDomainsChanged(CompanyEmailDomainsChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->changedByUserId, AuditAction::COMPANY_INVITATION_EMAIL_DOMAINS_CHANGED, self::SUBJECT_TYPE, $event->companyId,
            ['before' => $event->before, 'after' => $event->after], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyDocumentSubmitted(CompanyDocumentSubmitted $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::COMPANY_DOCUMENT_SUBMITTED,
            self::SUBJECT_TYPE, $event->companyId, ['document_type_id' => $event->documentTypeId, 'replaced' => $event->replaced],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyDocumentDeleted(CompanyDocumentDeleted $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::COMPANY_DOCUMENT_DELETED,
            self::SUBJECT_TYPE, $event->companyId, ['document_type_id' => $event->documentTypeId, 'status' => $event->status],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyDocumentApproved(CompanyDocumentApproved $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_DOCUMENT_APPROVED,
            self::SUBJECT_TYPE, $event->companyId, ['document_type_id' => $event->documentTypeId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyDocumentRejected(CompanyDocumentRejected $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_DOCUMENT_REJECTED,
            self::SUBJECT_TYPE, $event->companyId, ['document_type_id' => $event->documentTypeId, 'reason' => $event->reason, 'note' => $event->note],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyDocumentViewed(CompanyDocumentViewed $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_DOCUMENT_VIEWED,
            self::SUBJECT_TYPE, $event->companyId, ['document_type_id' => $event->documentTypeId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onDocumentTypeCreated(DocumentTypeCreated $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_DOCUMENT_TYPE_CREATED,
            self::DOCUMENT_TYPE_SUBJECT_TYPE, $event->typeId, ['slug' => $event->slug, 'is_required' => $event->isRequired, 'is_active' => $event->isActive],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onDocumentTypeVerificationRulesChanged(DocumentTypeVerificationRulesChanged $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::Admin, $event->adminId, AuditAction::COMPANY_DOCUMENT_TYPE_UPDATED,
            self::DOCUMENT_TYPE_SUBJECT_TYPE, $event->typeId,
            [
                'slug' => $event->slug,
                'old' => ['is_required' => $event->wasRequired, 'is_active' => $event->wasActive],
                'new' => ['is_required' => $event->isRequired, 'is_active' => $event->isActive],
            ],
            $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onCompanyMemberJoined(CompanyMemberJoined $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::User, $event->userId, AuditAction::COMPANY_MEMBER_JOINED,
            self::SUBJECT_TYPE, $event->companyId, ['user_id' => $event->userId, 'role' => $event->role], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
