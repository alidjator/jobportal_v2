<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Application\TwoFactor\TwoFactorAuthenticator;
use Src\Identity\Application\TwoFactor\TwoFactorChallengeIssued;
use Src\Identity\Application\TwoFactor\TwoFactorEnrolment;
use Src\Identity\Application\TwoFactor\TwoFactorTokens;
use Src\Identity\Application\TwoFactor\UserTwoFactorChallenges;
use Src\Identity\Application\TwoFactor\UserTwoFactors;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The first step of a psychologist's sign-in at the provider endpoints: email and password. A right password issues a 5-minute single-use
 * challenge, never a token; `CompletePsychologistSignIn` turns it into the 12-hour token once the code of the second factor is right. A
 * psychologist who has not confirmed a second factor yet also gets a fresh secret to enrol one. An account that is not a psychologist gets
 * the same answer as a wrong password.
 */
final readonly class SignInPsychologist
{
    public const string TOKEN_LIFETIME = '+12 hours';

    public const string CHALLENGE_LIFETIME = '+5 minutes';

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private SignInThrottle $throttle,
        private TwoFactorAuthenticator $authenticator,
        private TwoFactorTokens $twoFactorTokens,
        private UserTwoFactors $factors,
        private UserTwoFactorChallenges $challenges,
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
        $this->throttle->assertCredentialsNotLocked(AccountScope::User, $email, $command->ipAddress);

        $user = $this->users->findByEmail($email);

        if (! $this->hasher->check($command->password, $user?->passwordHash()) || $user === null || ! $user->isPsychologist()) {
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
        $expiresAt = $now->modify(self::CHALLENGE_LIFETIME);
        $token = $this->twoFactorTokens->challengeToken();
        $userId = (int) $user->id;

        return $this->transactions->run(function () use ($user, $userId, $token, $expiresAt, $now): TwoFactorChallengeIssued {
            $enrolment = null;

            if (! ($this->factors->find($userId)?->isConfirmed() ?? false)) {
                $secret = $this->authenticator->generateSecret();
                $this->factors->begin($userId, $secret, $now);

                $url = $this->authenticator->otpauthUrl($user->email(), $secret);
                $enrolment = new TwoFactorEnrolment($secret, $url, $this->authenticator->qrCode($url));
            }

            $this->challenges->issue($userId, $this->twoFactorTokens->hash($token), $expiresAt, $now);

            return new TwoFactorChallengeIssued($token, $expiresAt, $enrolment);
        });
    }

    private function failure(?int $accountId, string $reason, SignInCommand $command): SignInFailed
    {
        return new SignInFailed(AccountScope::User, $accountId, $reason, $command->ipAddress, $command->userAgent, $this->clock->now());
    }
}
