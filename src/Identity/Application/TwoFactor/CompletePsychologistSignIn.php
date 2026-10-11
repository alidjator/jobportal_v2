<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\PsychologistSignInResult;
use Src\Identity\Application\Auth\SignInPsychologist;
use Src\Identity\Application\Auth\SignInThrottle;
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

/**
 * The second step of a psychologist's sign-in: the challenge from the password step plus a code from the authenticator app (or a recovery
 * code). For a psychologist who is enrolling, the first right code confirms the second factor and the answer carries the recovery codes,
 * shown only this once. A wrong code counts in the sign-in throttle and leaves the challenge usable.
 */
final readonly class CompletePsychologistSignIn
{
    public const int RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private UserRepository $users,
        private UserTwoFactorChallenges $challenges,
        private UserRecoveryCodes $recoveryCodes,
        private UserTwoFactors $factors,
        private TwoFactorAuthenticator $authenticator,
        private TwoFactorTokens $twoFactorTokens,
        private AccessTokenIssuer $tokens,
        private SignInThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AuthenticationFailed `two-factor-challenge-expired` when the challenge is unknown, used or expired; `invalid-two-factor-code` for a wrong code
     * @throws TooManyAttempts
     * @throws AccessDenied when the account was suspended or deactivated after the password step
     */
    public function execute(CompletePsychologistSignInCommand $command): PsychologistSignInResult
    {
        $now = $this->clock->now();
        $challenge = $this->challenges->findUsable($this->twoFactorTokens->hash($command->challengeToken), $now);
        $user = $challenge === null ? null : $this->users->findById($challenge->userId);
        $factor = $challenge === null ? null : $this->factors->find($challenge->userId);

        if ($challenge === null || $user === null || ! $user->isPsychologist() || $factor === null) {
            throw new AuthenticationFailed('The sign-in has expired. Sign in again.', 'two-factor-challenge-expired');
        }

        $userId = (int) $user->id;
        $email = $user->email();
        $ip = $command->ipAddress ?? '';
        $this->throttle->assertCredentialsNotLocked(AccountScope::User, $email, $ip);
        $user->assertCanSignIn();

        $enrolling = ! $factor->isConfirmed();
        $step = null;
        $usedRecoveryCode = false;

        if ($command->code !== null) {
            $step = $this->authenticator->matchingStep($factor->secret, $command->code, $factor->lastStep);
        } elseif ($command->recoveryCode !== null && ! $enrolling) {
            $usedRecoveryCode = $this->recoveryCodes->consume($userId, $this->twoFactorTokens->hash($command->recoveryCode), $now);
        }

        if ($step === null && ! $usedRecoveryCode) {
            $this->throttle->recordCredentialFailure(AccountScope::User, $email, $ip);
            $this->events->dispatch([new SignInFailed(AccountScope::User, $userId, 'invalid-two-factor-code', $command->ipAddress, $command->userAgent, $now)]);

            throw new AuthenticationFailed('The verification code is incorrect.', 'invalid-two-factor-code');
        }

        $expiresAt = $now->modify(SignInPsychologist::TOKEN_LIFETIME);

        [$token, $newRecoveryCodes] = $this->transactions->run(function () use ($user, $userId, $challenge, $command, $step, $enrolling, $now, $expiresAt): array {
            $this->challenges->consume($challenge->id, $now);
            $codes = [];

            if ($step !== null && $enrolling) {
                $this->factors->confirm($userId, $step, $now);
                $codes = $this->twoFactorTokens->recoveryCodes(self::RECOVERY_CODE_COUNT);
                $this->recoveryCodes->replace($userId, array_map($this->twoFactorTokens->hash(...), $codes), $now);
            } elseif ($step !== null) {
                $this->factors->recordStep($userId, $step, $now);
            }

            $user->recordSignIn($now);
            $this->users->save($user);

            return [$this->tokens->issueForUser($userId, $command->deviceName, $expiresAt), $codes];
        });

        $this->throttle->clearCredentials(AccountScope::User, $email, $ip);
        $this->events->dispatch([new SignedIn(AccountScope::User, $userId, $command->ipAddress, $command->userAgent, $now)]);

        return new PsychologistSignInResult($token, $expiresAt, $user, $newRecoveryCodes);
    }
}
