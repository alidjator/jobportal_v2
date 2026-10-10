<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Admin;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\PasswordChanged;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Changes the password of the signed-in account (user or admin) and revokes every other token, so only the device that
 * made the change stays signed in. Needs the current password, so a hijacked session cannot take the account over; a wrong
 * one counts in the sign-in throttle of the account.
 */
final readonly class ChangePassword
{
    public function __construct(
        private UserRepository $users,
        private AdminRepository $admins,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private SignInThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `password-not-set` for an account created with Google, which sets one through the reset flow
     * @throws BusinessRuleViolation `invalid-password` when the current password is wrong
     * @throws TooManyAttempts after too many wrong passwords
     */
    public function execute(Actor $actor, int $currentTokenId, string $currentPassword, string $newPassword, ?string $ipAddress, ?string $userAgent): void
    {
        $accountId = (int) $actor->id;
        $scope = $actor->isAdmin() ? AccountScope::Admin : AccountScope::User;
        $account = $scope === AccountScope::Admin
            ? $this->admins->findById($accountId) ?? throw EntityNotFound::of('Admin', $accountId)
            : $this->users->findById($accountId) ?? throw EntityNotFound::of('User', $accountId);

        if ($account->passwordHash() === null) {
            throw new StateConflict('This account has no password. Set one through the password reset flow.', 'password-not-set');
        }

        $email = $account->email();
        $ipAddress ??= '';
        $this->throttle->assertCredentialsNotLocked($scope, $email, $ipAddress);

        if (! $this->hasher->check($currentPassword, $account->passwordHash())) {
            $this->throttle->recordCredentialFailure($scope, $email, $ipAddress);

            throw new BusinessRuleViolation('The current password is incorrect.', 'invalid-password');
        }

        $this->throttle->clearCredentials($scope, $email, $ipAddress);

        $account->changePassword($this->hasher->make($newPassword));

        $this->transactions->run(function () use ($account, $actor, $currentTokenId): void {
            if ($account instanceof Admin) {
                $this->admins->save($account);
            } else {
                $this->users->save($account);
            }

            $this->tokens->revokeOthers($actor, $currentTokenId);
        });

        $this->events->dispatch([new PasswordChanged($scope, $accountId, $ipAddress === '' ? null : $ipAddress, $userAgent, $this->clock->now())]);
    }
}
