<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Application\Auth\VerificationCodeIssuer;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A platform admin holding `users.manage` sends an active account a password reset code (a support request). The code goes to the account's own address, never to the admin; the request is audited. */
final readonly class SendUserPasswordReset
{
    public function __construct(
        private ManagedAccounts $accounts,
        private VerificationCodeIssuer $codes,
        private VerificationCodeSender $sender,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `account-not-active`, `account-not-correctable` for a psychologist
     * @throws TooManyAttempts when the account's own code limits are used up
     */
    public function execute(Actor $actor, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->accounts->find($actor, $userId);
        $this->accounts->assertActive($user);

        $issued = $this->transactions->run(function () use ($userId, $actor, $ipAddress, $userAgent) {
            $issued = $this->codes->issueForUser($userId, VerificationPurpose::PasswordReset);

            $this->audit->record(new AuditEntry(
                AuditActorType::Admin, (int) $actor->id, AuditAction::ACCOUNT_PASSWORD_RESET_SENT, AccountScope::User->value, $userId, [], $ipAddress, $userAgent, $this->clock->now(),
            ));

            return $issued;
        });

        $this->sender->sendPasswordReset($user->email(), $user->name(), $user->locale(), $issued->code, $issued->expiresAt);
    }
}
