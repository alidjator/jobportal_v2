<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Application\Auth\TwoFactorSignInResult;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\UserRecoveryCodeUsed;
use Src\Identity\Domain\Events\UserTwoFactorEnabled;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\MemberSignInGuard;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The second step of a sign-in with a second factor, for the accounts of one type (a psychologist or a company member): the challenge from the password
 * step plus a code from the authenticator app (or a recovery code). For an account that is enrolling, the first right code confirms the second factor and
 * the answer carries the recovery codes, shown only this once. A wrong code counts in the sign-in throttle and leaves the challenge usable.
 */
final readonly class CompleteTwoFactorSignIn
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
        private MemberSignInGuard $memberships,
        private SignInThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  string|callable(int): string  $tokenLifetime  a `DateTimeImmutable::modify` interval for the token, or a function of the account id that gives it
     *
     * @throws AuthenticationFailed `two-factor-challenge-expired` when the challenge is unknown, used, expired or of an account of another type; `invalid-two-factor-code` for a wrong code
     * @throws TooManyAttempts
     * @throws AccessDenied when the account was suspended or deactivated after the password step
     */
    public function execute(CompleteTwoFactorSignInCommand $command, UserType $type, string|callable $tokenLifetime): TwoFactorSignInResult
    {
        $now = $this->clock->now();
        $challenge = $this->challenges->findUsable($this->twoFactorTokens->hash($command->challengeToken), $now);
        $user = $challenge === null ? null : $this->users->findById($challenge->userId);
        $factor = $challenge === null ? null : $this->factors->find($challenge->userId);

        if ($challenge === null || $user === null || $user->type !== $type || $factor === null) {
            throw new AuthenticationFailed('The sign-in has expired. Sign in again.', 'two-factor-challenge-expired');
        }

        $userId = (int) $user->id;
        $email = $user->email();
        $ip = $command->ipAddress ?? '';
        $this->throttle->assertCredentialsNotLocked(AccountScope::User, $email, $ip);
        $user->assertCanSignIn();

        if ($type === UserType::CompanyMember) {
            $this->memberships->assertMayPass($userId);
        }

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

        $expiresAt = $now->modify(is_string($tokenLifetime) ? $tokenLifetime : $tokenLifetime($userId));

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
        $this->events->dispatch([
            ...($step !== null && $enrolling ? [new UserTwoFactorEnabled($userId, $command->ipAddress, $command->userAgent, $now)] : []),
            ...($usedRecoveryCode ? [new UserRecoveryCodeUsed($userId, $command->ipAddress, $command->userAgent, $now)] : []),
            new SignedIn(AccountScope::User, $userId, $command->ipAddress, $command->userAgent, $now),
        ]);

        return new TwoFactorSignInResult($token, $expiresAt, $user, $newRecoveryCodes);
    }
}
