<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\Auth\SignInCommand;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Application\Auth\SignInUser;
use Src\Identity\Application\Auth\UserSignInResult;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\AccountDeletionCancelled;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Cancels a pending account deletion with valid credentials (the account cannot sign in while it is deactivated, so this
 * is not an authenticated endpoint), reactivates the account and signs the user in. Wrong credentials count against the
 * same failed-attempt limit as signing in.
 */
final readonly class CancelAccountDeletion
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AccessTokenIssuer $tokens,
        private SignInThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws TooManyAttempts
     * @throws AuthenticationFailed when the credentials are wrong
     * @throws StateConflict `deletion-not-requested` when no deletion is pending for the account
     */
    public function execute(SignInCommand $command): UserSignInResult
    {
        $email = mb_strtolower(trim($command->email));
        $this->throttle->assertCredentialsNotLocked(AccountScope::User, $email, $command->ipAddress);

        $user = $this->users->findByEmail($email);

        if (! $this->hasher->check($command->password, $user?->passwordHash()) || $user === null || ! $user->usesPublicSignIn()) {
            $this->throttle->recordCredentialFailure(AccountScope::User, $email, $command->ipAddress);
            $this->events->dispatch([new SignInFailed(AccountScope::User, $user?->id, 'invalid-credentials', $command->ipAddress, $command->userAgent, $this->clock->now())]);

            throw new AuthenticationFailed('The email or password is incorrect.');
        }

        $now = $this->clock->now();
        $expiresAt = $now->modify(SignInUser::TOKEN_LIFETIME);

        $token = $this->transactions->run(function () use ($user, $command, $now, $expiresAt): string {
            $user->cancelDeletion();
            $user->recordSignIn($now);
            $this->users->save($user);

            return $this->tokens->issueForUser((int) $user->id, $command->deviceName, $expiresAt);
        });

        $this->throttle->clearCredentials(AccountScope::User, $email, $command->ipAddress);
        $this->events->dispatch([
            new AccountDeletionCancelled((int) $user->id, $command->ipAddress, $command->userAgent, $now),
            new SignedIn(AccountScope::User, (int) $user->id, $command->ipAddress, $command->userAgent, $now),
        ]);

        return new UserSignInResult($token, $expiresAt, $user);
    }
}
