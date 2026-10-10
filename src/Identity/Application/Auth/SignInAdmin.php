<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Application\TwoFactor\TwoFactorAuthenticator;
use Src\Identity\Application\TwoFactor\TwoFactorChallengeIssued;
use Src\Identity\Application\TwoFactor\TwoFactorChallenges;
use Src\Identity\Application\TwoFactor\TwoFactorEnrolment;
use Src\Identity\Application\TwoFactor\TwoFactorTokens;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The first step of an administrator sign-in: email and password. A right password issues a 5-minute single-use challenge,
 * never a token; `CompleteAdminSignIn` turns it into the 12-hour token once the code of the second factor is right. An admin
 * who has not confirmed a second factor yet also gets a fresh secret to enrol one.
 */
final readonly class SignInAdmin
{
    public const string TOKEN_LIFETIME = '+12 hours';

    public const string CHALLENGE_LIFETIME = '+5 minutes';

    public function __construct(
        private AdminRepository $admins,
        private PasswordHasher $hasher,
        private SignInThrottle $throttle,
        private TwoFactorAuthenticator $authenticator,
        private TwoFactorTokens $twoFactorTokens,
        private TwoFactorChallenges $challenges,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws TooManyAttempts
     * @throws AuthenticationFailed
     * @throws AccessDenied
     */
    public function execute(SignInCommand $command): TwoFactorChallengeIssued
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
        $expiresAt = $now->modify(self::CHALLENGE_LIFETIME);
        $token = $this->twoFactorTokens->challengeToken();

        return $this->transactions->run(function () use ($admin, $token, $expiresAt, $now): TwoFactorChallengeIssued {
            $enrolment = null;

            if (! $admin->hasTwoFactor()) {
                $secret = $this->authenticator->generateSecret();
                $admin->beginTwoFactorEnrolment($secret);
                $this->admins->save($admin);

                $url = $this->authenticator->otpauthUrl($admin->email(), $secret);
                $enrolment = new TwoFactorEnrolment($secret, $url, $this->authenticator->qrCode($url));
            }

            $this->challenges->issue((int) $admin->id, $this->twoFactorTokens->hash($token), $expiresAt, $now);

            return new TwoFactorChallengeIssued($token, $expiresAt, $enrolment);
        });
    }

    private function failure(?int $accountId, string $reason, SignInCommand $command): SignInFailed
    {
        return new SignInFailed(AccountScope::Admin, $accountId, $reason, $command->ipAddress, $command->userAgent, $this->clock->now());
    }
}
