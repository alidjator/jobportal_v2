<?php

namespace Src\Identity\Application\Account;

use DateTimeImmutable;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Domain\Events\AccountDeletionRequested;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMemberDeletionGuard;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Starts the 30-day deletion grace period: the account is deactivated and every token is revoked, so the user is signed
 * out everywhere. The user can still cancel (see `CancelAccountDeletion`). Needs the current password, so a hijacked session
 * cannot do it; an account without a password must set one first (through the password reset flow). The only company admin of a company cannot
 * delete the account (`last-company-admin`, the rule of `RemoveMember`); the company's own listener releases any other member once the account is anonymised.
 */
final readonly class RequestAccountDeletion
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private SignInThrottle $throttle,
        private AccessTokenIssuer $tokens,
        private TransactionManager $transactions,
        private CompanyMemberDeletionGuard $companyGuard,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return DateTimeImmutable when the account will be anonymised
     *
     * @throws EntityNotFound
     * @throws AccessDenied `psychologist-account-managed` for a psychologist
     * @throws BusinessRuleViolation `password-required` for an account without a password, `invalid-password` for a wrong one
     * @throws StateConflict when a deletion is already pending or the account is not active, or `last-company-admin` for the only company admin of a company
     * @throws TooManyAttempts after too many wrong passwords
     */
    public function execute(Actor $actor, string $password, ?string $ipAddress, ?string $userAgent): DateTimeImmutable
    {
        $user = $this->users->findById((int) $actor->id) ?? throw EntityNotFound::of('User', $actor->id);

        // The signed reports of a psychologist must keep an author, so an administrator closes the account instead.
        if ($user->isPsychologist()) {
            throw new AccessDenied('A psychologist account is closed by an administrator.', 'psychologist-account-managed');
        }

        if ($user->passwordHash() === null) {
            throw new BusinessRuleViolation('Set a password before deleting the account.', 'password-required');
        }

        $throttleKey = 'account-deletion|'.$user->id.'|'.$ipAddress;
        $this->throttle->assertNotLocked($throttleKey);

        if (! $this->hasher->check($password, $user->passwordHash())) {
            $this->throttle->recordFailure($throttleKey);

            throw new BusinessRuleViolation('The password is incorrect.', 'invalid-password');
        }

        $this->throttle->clear($throttleKey);

        $now = $this->clock->now();
        $user->requestDeletion($now);
        $scheduledFor = $user->deletionDueAt() ?? $now;

        $this->transactions->run(function () use ($user): void {
            $this->companyGuard->assertCanRequestDeletion((int) $user->id);
            $this->users->save($user);
            $this->tokens->revokeAllForUser((int) $user->id);
        });

        $this->events->dispatch([new AccountDeletionRequested((int) $user->id, $scheduledFor, $ipAddress, $userAgent, $now)]);

        return $scheduledFor;
    }
}
