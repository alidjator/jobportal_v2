<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Signs an administrator in with email and password and issues a 12-hour token. */
final readonly class SignInAdmin
{
    public const string TOKEN_LIFETIME = '+12 hours';

    public function __construct(
        private AdminRepository $admins,
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
    public function execute(SignInCommand $command): AdminSignInResult
    {
        $email = mb_strtolower(trim($command->email));
        $this->throttle->assertCredentialsNotLocked(AccountScope::Admin, $email, $command->ipAddress);

        $admin = $this->admins->findByEmail($email);

        if (! $this->hasher->check($command->password, $admin?->passwordHash()) || $admin === null) {
            $this->throttle->recordCredentialFailure(AccountScope::Admin, $email, $command->ipAddress);
            $this->events->dispatch([$this->failure($admin?->id, 'invalid-credentials', $command)]);

            throw new AuthenticationFailed('The email or password is incorrect.');
        }

        try {
            $admin->assertCanSignIn();
        } catch (AccessDenied $e) {
            $this->events->dispatch([$this->failure($admin->id, $e->errorCode(), $command)]);

            throw $e;
        }

        $now = $this->clock->now();
        $expiresAt = $now->modify(self::TOKEN_LIFETIME);

        $token = $this->transactions->run(function () use ($admin, $command, $now, $expiresAt): string {
            $admin->recordSignIn($now);
            $this->admins->save($admin);

            return $this->tokens->issueForAdmin((int) $admin->id, $command->deviceName, $expiresAt);
        });

        $this->throttle->clearCredentials(AccountScope::Admin, $email, $command->ipAddress);
        $this->events->dispatch([new SignedIn(AccountScope::Admin, (int) $admin->id, $command->ipAddress, $command->userAgent, $now)]);

        return new AdminSignInResult($token, $expiresAt, $admin);
    }

    private function failure(?int $accountId, string $reason, SignInCommand $command): SignInFailed
    {
        return new SignInFailed(AccountScope::Admin, $accountId, $reason, $command->ipAddress, $command->userAgent, $this->clock->now());
    }
}
