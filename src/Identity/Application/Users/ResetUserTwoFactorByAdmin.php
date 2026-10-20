<?php

namespace Src\Identity\Application\Users;

use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\TwoFactor\UserTwoFactors;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\AccountTwoFactorResetByAdmin;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin holding `users.manage` removes the second factor of a job seeker or company member who lost the device, after the written identity check of
 * `docs/design/phase-8-activation.md` (Appendix B). The secret, the recovery codes and the open challenges go and every token is revoked; the next sign-in asks for enrolment when the
 * account must have a factor. The account is told by email (and a company member in-app, through the event), and the reset is audited with the reason and the proofs seen.
 */
final readonly class ResetUserTwoFactorByAdmin
{
    public function __construct(
        private ManagedAccounts $accounts,
        private UserTwoFactors $factors,
        private AccessTokenIssuer $tokens,
        private AccountNoticeSender $notices,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `account-not-correctable` for a psychologist
     */
    public function execute(Actor $actor, int $userId, string $reason, string $identityCheck, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->accounts->find($actor, $userId);
        $now = $this->clock->now();

        $this->transactions->run(function () use ($userId, $actor, $reason, $identityCheck, $ipAddress, $userAgent, $now): void {
            $this->factors->reset($userId);
            $this->tokens->revokeAllForUser($userId);

            $this->audit->record(new AuditEntry(
                AuditActorType::Admin, (int) $actor->id, AuditAction::ACCOUNT_TWO_FACTOR_RESET, AccountScope::User->value, $userId,
                ['reason' => $reason, 'identity_check' => $identityCheck], $ipAddress, $userAgent, $now,
            ));
        });

        $this->notices->twoFactorReset($user->email(), $user->name(), $user->locale());
        $this->events->dispatch([new AccountTwoFactorResetByAdmin($userId, (int) $actor->id, $now)]);
    }
}
