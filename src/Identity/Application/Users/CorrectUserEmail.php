<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Auth\AccessTokenIssuer;
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
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin holding `users.manage` corrects the sign-in email of a job seeker or company member who lost the old mailbox, after the written identity check of
 * `docs/design/phase-8-activation.md` (Appendix B). The new address counts as unverified; every token is revoked; a pending self-service change is dropped; the change is audited
 * with the reason and the proofs seen. The old address is told, and the new one gets the link where the owner sets a password with a code. The company's email-domain limit does not apply.
 */
final readonly class CorrectUserEmail
{
    public function __construct(
        private ManagedAccounts $accounts,
        private UserRepository $users,
        private EmailChangeRepository $changes,
        private EmailSuppressions $suppressions,
        private AccessTokenIssuer $tokens,
        private AccountNoticeSender $notices,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `account-not-correctable` for a psychologist, `email-in-use` when an account has the address already
     * @throws BusinessRuleViolation `email-undeliverable` for an address on the suppression list
     */
    public function execute(Actor $actor, int $userId, string $email, string $reason, string $identityCheck, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->accounts->find($actor, $userId);
        $new = mb_strtolower(trim($email));

        if ($this->users->findByEmail($new) !== null) {
            throw new StateConflict('An account already uses this email address.', 'email-in-use');
        }

        if ($this->suppressions->reason($new) !== null) {
            throw new BusinessRuleViolation('This address does not receive email. Use another address.', 'email-undeliverable');
        }

        $old = $user->email();
        $user->changeEmail($new, null);

        $this->transactions->run(function () use ($user, $userId, $actor, $old, $new, $reason, $identityCheck, $ipAddress, $userAgent): void {
            $this->users->save($user);
            $this->changes->delete($userId);
            $this->tokens->revokeAllForUser($userId);

            $this->audit->record(new AuditEntry(
                AuditActorType::Admin, (int) $actor->id, AuditAction::ACCOUNT_EMAIL_CORRECTED, AccountScope::User->value, $userId,
                ['reason' => $reason, 'identity_check' => $identityCheck, 'old_email' => MaskedEmail::of($old), 'new_email' => MaskedEmail::of($new)],
                $ipAddress, $userAgent, $this->clock->now(),
            ));
        });

        $this->notices->emailCorrectedOld($old, $user->name(), $user->locale(), $new);
        $this->notices->emailCorrectedNew($new, $user->name(), $user->locale(), $user->type);
    }
}
