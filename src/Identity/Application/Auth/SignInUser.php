<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Signs a public user (job seeker or company member) in with email and password and issues a 30-day token. */
final readonly class SignInUser
{
    public const string TOKEN_LIFETIME = '+30 days';

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
     * @throws AuthenticationFailed
     * @throws AccessDenied
     */
    public function execute(SignInCommand $command): UserSignInResult
    {
        $email = mb_strtolower(trim($command->email));
        $this->throttle->assertCredentialsNotLocked(AccountScope::User, $email, $command->ipAddress);

        $user = $this->users->findByEmail($email);

        if (! $this->hasher->check($command->password, $user?->passwordHash()) || $user === null) {
            $this->throttle->recordCredentialFailure(AccountScope::User, $email, $command->ipAddress);
            $this->events->dispatch([$this->failure($user?->id, 'invalid-credentials', $command)]);

            throw new AuthenticationFailed('The email or password is incorrect.');
        }

        try {
            $user->assertCanSignIn();
        } catch (AccessDenied $e) {
            $this->events->dispatch([$this->failure($user->id, $e->errorCode(), $command)]);

            throw $e;
        }

        $now = $this->clock->now();
        $expiresAt = $now->modify(self::TOKEN_LIFETIME);

        $token = $this->transactions->run(function () use ($user, $command, $now, $expiresAt): string {
            $user->recordSignIn($now);
            $this->users->save($user);

            return $this->tokens->issueForUser((int) $user->id, $command->deviceName, $expiresAt);
        });

        $this->throttle->clearCredentials(AccountScope::User, $email, $command->ipAddress);
        $this->events->dispatch([new SignedIn(AccountScope::User, (int) $user->id, $command->ipAddress, $command->userAgent, $now)]);

        return new UserSignInResult($token, $expiresAt, $user);
    }

    private function failure(?int $accountId, string $reason, SignInCommand $command): SignInFailed
    {
        return new SignInFailed(AccountScope::User, $accountId, $reason, $command->ipAddress, $command->userAgent, $this->clock->now());
    }
}
