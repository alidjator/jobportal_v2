<?php

namespace Src\Identity\Application\EmailChange;

use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Application\Auth\VerificationCodeHasher;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\EmailChangeRepository;
use Src\Identity\Domain\MaskedEmail;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Confirms the pending email change with the code sent to the new address and the account's password; an account without a password gives the second code sent to its current address
 * instead. The new address counts as verified (the code proved the mailbox), every other session is signed out, the old address is told and the change is audited.
 */
final readonly class ConfirmEmailChange
{
    public function __construct(
        private UserRepository $users,
        private EmailChangeRepository $changes,
        private EmailChangeThrottle $throttle,
        private VerificationCodeHasher $codeHasher,
        private PasswordHasher $passwords,
        private SignInThrottle $signIns,
        private AccessTokenIssuer $tokens,
        private AccountNoticeSender $notices,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `email-change-expired` (nothing pending, expired or too many wrong codes), `email-change-code-invalid`, `invalid-password`
     * @throws StateConflict `email-in-use` when another account took the address meanwhile
     * @throws TooManyAttempts after too many wrong passwords, or when the account changed its email less than 24 hours ago
     */
    public function execute(Actor $actor, int $currentTokenId, string $code, ?string $password, ?string $oldCode, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);
        $userId = (int) $user->id;
        $now = $this->clock->now();

        $change = $this->changes->findByUser($userId);

        if ($change === null || ! $change->isUsable($now)) {
            throw new BusinessRuleViolation('There is no pending email change, or it has expired. Request a new code.', 'email-change-expired');
        }

        $this->throttle->assertNotChangedRecently($userId);
        $this->assertPassword($user->passwordHash(), $password, $user->email(), $ipAddress ?? '');

        $codesMatch = $this->codeHasher->matches(trim($code), $change->newCodeHash)
            && ($change->oldCodeHash === null || ($oldCode !== null && $this->codeHasher->matches(trim($oldCode), $change->oldCodeHash)));

        if (! $codesMatch) {
            // Saved outside the transaction below, so a wrong attempt always counts.
            $change->recordFailedAttempt();
            $this->changes->saveAttempts($change);

            throw new BusinessRuleViolation('The code is incorrect.', 'email-change-code-invalid');
        }

        if ($this->users->findByEmail($change->newEmail) !== null) {
            throw new StateConflict('Another account already uses this email address.', 'email-in-use');
        }

        $oldEmail = $user->email();
        $user->changeEmail($change->newEmail, $now);

        $this->transactions->run(function () use ($user, $userId, $actor, $currentTokenId, $oldEmail, $change, $ipAddress, $userAgent, $now): void {
            $this->users->save($user);
            $this->changes->delete($userId);
            $this->tokens->revokeOthers($actor, $currentTokenId);

            $this->audit->record(new AuditEntry(
                AuditActorType::User, $userId, AuditAction::ACCOUNT_EMAIL_CHANGED, AccountScope::User->value, $userId,
                ['old_email' => MaskedEmail::of($oldEmail), 'new_email' => MaskedEmail::of($change->newEmail)], $ipAddress, $userAgent, $now,
            ));
        });

        $this->throttle->recordChange($userId);
        $this->notices->emailChanged($oldEmail, $user->name(), $user->locale(), $user->email());
    }

    /** @throws BusinessRuleViolation `invalid-password` */
    private function assertPassword(?string $hash, ?string $password, string $email, string $ipAddress): void
    {
        if ($hash === null) {
            return;
        }

        $this->signIns->assertCredentialsNotLocked(AccountScope::User, $email, $ipAddress);

        if ($password === null || ! $this->passwords->check($password, $hash)) {
            $this->signIns->recordCredentialFailure(AccountScope::User, $email, $ipAddress);

            throw new BusinessRuleViolation('The password is incorrect.', 'invalid-password');
        }

        $this->signIns->clearCredentials(AccountScope::User, $email, $ipAddress);
    }
}
