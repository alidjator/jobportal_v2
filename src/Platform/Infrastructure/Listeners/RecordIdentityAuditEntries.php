<?php

namespace Src\Platform\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\AccountDeletionCancelled;
use Src\Identity\Domain\Events\AccountDeletionRequested;
use Src\Identity\Domain\Events\PasswordChanged;
use Src\Identity\Domain\Events\PasswordReset;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignedOutEverywhere;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for Identity's security events: sign-in (success and failure), password reset and change, sign-out from every device and the account
 * deletion lifecycle. Runs on the `default` queue, retried 3 times with a growing delay. The entries hold no sensitive values:
 * never the email address that was typed, a password or a code.
 */
final class RecordIdentityAuditEntries implements ShouldQueue
{
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

    public function onSignedIn(SignedIn $event): void
    {
        $this->record->execute(new AuditEntry(
            $this->actorType($event->scope), $event->accountId, AuditAction::LOGIN,
            $event->scope->value, $event->accountId, [], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onSignInFailed(SignInFailed $event): void
    {
        $this->record->execute(new AuditEntry(
            $this->actorType($event->scope), $event->accountId, AuditAction::LOGIN_FAILED,
            $event->accountId === null ? null : $event->scope->value, $event->accountId,
            ['reason' => $event->reason], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->record->execute($this->userEntry(AuditAction::PASSWORD_RESET, $event->userId, [], $event->ipAddress, $event->userAgent, $event));
    }

    public function onPasswordChanged(PasswordChanged $event): void
    {
        $this->record->execute($this->accountEntry(AuditAction::PASSWORD_CHANGED, $event));
    }

    public function onSignedOutEverywhere(SignedOutEverywhere $event): void
    {
        $this->record->execute($this->accountEntry(AuditAction::SIGNED_OUT_EVERYWHERE, $event));
    }

    public function onAccountDeletionRequested(AccountDeletionRequested $event): void
    {
        $this->record->execute($this->userEntry(
            AuditAction::ACCOUNT_DELETION_REQUESTED, $event->userId,
            ['scheduled_for' => $event->scheduledFor->format(DATE_ATOM)], $event->ipAddress, $event->userAgent, $event,
        ));
    }

    public function onAccountDeletionCancelled(AccountDeletionCancelled $event): void
    {
        $this->record->execute($this->userEntry(AuditAction::ACCOUNT_DELETION_CANCELLED, $event->userId, [], $event->ipAddress, $event->userAgent, $event));
    }

    public function onUserAnonymized(UserAnonymized $event): void
    {
        $this->record->execute(new AuditEntry(
            AuditActorType::System, null, AuditAction::ACCOUNT_ANONYMIZED,
            AccountScope::User->value, $event->userId, [], null, null, $event->occurredAt(),
        ));
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

    /** @param  array<string, scalar|null>  $metadata */
    private function userEntry(string $action, int $userId, array $metadata, ?string $ip, ?string $userAgent, PasswordReset|AccountDeletionRequested|AccountDeletionCancelled $event): AuditEntry
    {
        return new AuditEntry(AuditActorType::User, $userId, $action, AccountScope::User->value, $userId, $metadata, $ip, $userAgent, $event->occurredAt());
    }
}
