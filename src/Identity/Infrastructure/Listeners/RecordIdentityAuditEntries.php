<?php

namespace Src\Identity\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\AccountDeletionCancelled;
use Src\Identity\Domain\Events\AccountDeletionRequested;
use Src\Identity\Domain\Events\AdminPasswordReset;
use Src\Identity\Domain\Events\AdminReactivated;
use Src\Identity\Domain\Events\AdminRoleChanged;
use Src\Identity\Domain\Events\AdminRoleCreated;
use Src\Identity\Domain\Events\AdminRoleDeleted;
use Src\Identity\Domain\Events\AdminRolePermissionsChanged;
use Src\Identity\Domain\Events\AdminSuspended;
use Src\Identity\Domain\Events\PasswordChanged;
use Src\Identity\Domain\Events\PasswordReset;
use Src\Identity\Domain\Events\RecoveryCodesRegenerated;
use Src\Identity\Domain\Events\RecoveryCodeUsed;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignedOutEverywhere;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\TwoFactorEnabled;
use Src\Identity\Domain\Events\TwoFactorReset;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Domain\Events\UserRecoveryCodesRegenerated;
use Src\Identity\Domain\Events\UserRecoveryCodeUsed;
use Src\Identity\Domain\Events\UserTwoFactorDisabled;
use Src\Identity\Domain\Events\UserTwoFactorEnabled;
use Src\Identity\Domain\Events\UserTwoFactorReset;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for Identity's security events: sign-in (success and failure), password reset and change, sign-out from every device, the admin second factor and the account
 * deletion lifecycle, and the operator's console changes to admin roles and administrators. Runs on the `default` queue, retried 3 times with a growing delay. The entries hold no sensitive values:
 * never the email address that was typed, a password or a code.
 */
final class RecordIdentityAuditEntries implements ShouldQueue
{
    public int $tries = 3;

    /** The subject type of an audit entry about an admin role. */
    private const string ROLE_SUBJECT = 'admin_role';

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

    public function onSignedIn(SignedIn $event): void
    {
        $this->trail->record(new AuditEntry(
            $this->actorType($event->scope), $event->accountId, AuditAction::LOGIN,
            $event->scope->value, $event->accountId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onSignInFailed(SignInFailed $event): void
    {
        $this->trail->record(new AuditEntry(
            $this->actorType($event->scope), $event->accountId, AuditAction::LOGIN_FAILED,
            $event->accountId === null ? null : $event->scope->value, $event->accountId,
            ['reason' => $event->reason], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->trail->record($this->userEntry(AuditAction::PASSWORD_RESET, $event->userId, [], $event->ipAddress, $event->userAgent, $event));
    }

    public function onPasswordChanged(PasswordChanged $event): void
    {
        $this->trail->record($this->accountEntry(AuditAction::PASSWORD_CHANGED, $event));
    }

    public function onSignedOutEverywhere(SignedOutEverywhere $event): void
    {
        $this->trail->record($this->accountEntry(AuditAction::SIGNED_OUT_EVERYWHERE, $event));
    }

    public function onTwoFactorEnabled(TwoFactorEnabled $event): void
    {
        $this->trail->record($this->adminEntry(AuditAction::TWO_FACTOR_ENABLED, $event));
    }

    public function onRecoveryCodeUsed(RecoveryCodeUsed $event): void
    {
        $this->trail->record($this->adminEntry(AuditAction::RECOVERY_CODE_USED, $event));
    }

    public function onRecoveryCodesRegenerated(RecoveryCodesRegenerated $event): void
    {
        $this->trail->record($this->adminEntry(AuditAction::RECOVERY_CODES_REGENERATED, $event));
    }

    /** Done by an operator on the console, so the actor is the system. */
    public function onTwoFactorReset(TwoFactorReset $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::System, null, AuditAction::TWO_FACTOR_RESET,
            AccountScope::Admin->value, $event->adminId, [], null, null, $event->occurredAt(),
        ));
    }

    public function onUserTwoFactorEnabled(UserTwoFactorEnabled $event): void
    {
        $this->trail->record($this->ownFactorEntry(AuditAction::TWO_FACTOR_ENABLED, $event));
    }

    public function onUserTwoFactorDisabled(UserTwoFactorDisabled $event): void
    {
        $this->trail->record($this->ownFactorEntry(AuditAction::TWO_FACTOR_DISABLED, $event));
    }

    public function onUserRecoveryCodeUsed(UserRecoveryCodeUsed $event): void
    {
        $this->trail->record($this->ownFactorEntry(AuditAction::RECOVERY_CODE_USED, $event));
    }

    public function onUserRecoveryCodesRegenerated(UserRecoveryCodesRegenerated $event): void
    {
        $this->trail->record($this->ownFactorEntry(AuditAction::RECOVERY_CODES_REGENERATED, $event));
    }

    /** Done by an operator on the console, so the actor is the system. */
    public function onUserTwoFactorReset(UserTwoFactorReset $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::System, null, AuditAction::TWO_FACTOR_RESET,
            AccountScope::User->value, $event->userId, ['operator' => $event->operator], null, null, $event->occurredAt(),
        ));
    }

    public function onAccountDeletionRequested(AccountDeletionRequested $event): void
    {
        $this->trail->record($this->userEntry(
            AuditAction::ACCOUNT_DELETION_REQUESTED, $event->userId,
            ['scheduled_for' => $event->scheduledFor->format(DATE_ATOM)], $event->ipAddress, $event->userAgent, $event,
        ));
    }

    public function onAccountDeletionCancelled(AccountDeletionCancelled $event): void
    {
        $this->trail->record($this->userEntry(AuditAction::ACCOUNT_DELETION_CANCELLED, $event->userId, [], $event->ipAddress, $event->userAgent, $event));
    }

    public function onUserAnonymized(UserAnonymized $event): void
    {
        $this->trail->record(new AuditEntry(
            AuditActorType::System, null, AuditAction::ACCOUNT_ANONYMIZED,
            AccountScope::User->value, $event->userId, [], null, null, $event->occurredAt(),
        ));
    }

    public function onAdminRoleCreated(AdminRoleCreated $event): void
    {
        $this->trail->record($this->roleEntry(AuditAction::ADMIN_ROLE_CREATED, $event->role, ['permissions' => $event->permissions], $event->operator, $event));
    }

    public function onAdminRolePermissionsChanged(AdminRolePermissionsChanged $event): void
    {
        $this->trail->record($this->roleEntry(
            AuditAction::ADMIN_ROLE_PERMISSIONS_CHANGED, $event->role, ['added' => $event->added, 'removed' => $event->removed], $event->operator, $event,
        ));
    }

    public function onAdminRoleDeleted(AdminRoleDeleted $event): void
    {
        $this->trail->record($this->roleEntry(AuditAction::ADMIN_ROLE_DELETED, $event->role, [], $event->operator, $event));
    }

    public function onAdminRoleChanged(AdminRoleChanged $event): void
    {
        $this->trail->record($this->operatorEntry(
            AuditAction::ADMIN_ROLE_CHANGED, $event->adminId, ['from' => $event->previousRole, 'to' => $event->role], $event->operator, $event,
        ));
    }

    public function onAdminSuspended(AdminSuspended $event): void
    {
        $this->trail->record($this->operatorEntry(AuditAction::ADMIN_SUSPENDED, $event->adminId, [], $event->operator, $event));
    }

    public function onAdminReactivated(AdminReactivated $event): void
    {
        $this->trail->record($this->operatorEntry(AuditAction::ADMIN_REACTIVATED, $event->adminId, [], $event->operator, $event));
    }

    public function onAdminPasswordReset(AdminPasswordReset $event): void
    {
        $this->trail->record($this->operatorEntry(AuditAction::ADMIN_PASSWORD_RESET, $event->adminId, [], $event->operator, $event));
    }

    private function actorType(AccountScope $scope): AuditActorType
    {
        return $scope === AccountScope::Admin ? AuditActorType::Admin : AuditActorType::User;
    }

    private function accountEntry(string $action, PasswordChanged|SignedOutEverywhere $event): AuditEntry
    {
        return new AuditEntry(
            $this->actorType($event->scope), $event->accountId, $action,
            $event->scope->value, $event->accountId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        );
    }

    private function adminEntry(string $action, TwoFactorEnabled|RecoveryCodeUsed|RecoveryCodesRegenerated $event): AuditEntry
    {
        return new AuditEntry(
            AuditActorType::Admin, $event->adminId, $action,
            AccountScope::Admin->value, $event->adminId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        );
    }

    private function ownFactorEntry(string $action, UserTwoFactorEnabled|UserTwoFactorDisabled|UserRecoveryCodeUsed|UserRecoveryCodesRegenerated $event): AuditEntry
    {
        return new AuditEntry(AuditActorType::User, $event->userId, $action, AccountScope::User->value, $event->userId, [], $event->ipAddress, $event->userAgent, $event->occurredAt());
    }

    /** @param  array<string, scalar|null>  $metadata */
    private function userEntry(string $action, int $userId, array $metadata, ?string $ip, ?string $userAgent, PasswordReset|AccountDeletionRequested|AccountDeletionCancelled $event): AuditEntry
    {
        return new AuditEntry(AuditActorType::User, $userId, $action, AccountScope::User->value, $userId, $metadata, $ip, $userAgent, $event->occurredAt());
    }

    /**
     * An operator's console change to an administrator: the system acts, the operating-system user is kept in the metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function operatorEntry(string $action, int $adminId, array $metadata, string $operator, DomainEvent $event): AuditEntry
    {
        return new AuditEntry(
            AuditActorType::System, null, $action,
            AccountScope::Admin->value, $adminId, [...$metadata, 'operator' => $operator], null, null, $event->occurredAt(),
        );
    }

    /**
     * An operator's console change to a role; the role has no id worth keeping, so its name is in the metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function roleEntry(string $action, string $role, array $metadata, string $operator, DomainEvent $event): AuditEntry
    {
        return new AuditEntry(
            AuditActorType::System, null, $action,
            self::ROLE_SUBJECT, null, ['role' => $role, ...$metadata, 'operator' => $operator], null, null, $event->occurredAt(),
        );
    }
}
