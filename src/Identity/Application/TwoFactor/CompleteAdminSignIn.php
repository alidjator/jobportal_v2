<?php

namespace Src\Identity\Application\TwoFactor;

use DateTimeImmutable;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\Auth\AdminSignInResult;
use Src\Identity\Application\Auth\SignInAdmin;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Admin;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\RecoveryCodeUsed;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Domain\Events\TwoFactorEnabled;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The second step of an administrator sign-in: the challenge from the password step plus a code from the authenticator app
 * (or a recovery code). For an admin who is enrolling, the first right code confirms the second factor and the answer carries
 * the recovery codes, shown only this once. A wrong code counts in the sign-in throttle and leaves the challenge usable.
 */
final readonly class CompleteAdminSignIn
{
    public const int RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private AdminRepository $admins,
        private TwoFactorChallenges $challenges,
        private RecoveryCodes $recoveryCodes,
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
    public function execute(CompleteAdminSignInCommand $command): AdminSignInResult
    {
        $now = $this->clock->now();
        $challenge = $this->challenges->findUsable($this->twoFactorTokens->hash($command->challengeToken), $now);
        $admin = $challenge === null ? null : $this->admins->findById($challenge->adminId);

        if ($challenge === null || $admin === null) {
            throw new AuthenticationFailed('The sign-in has expired. Sign in again.', 'two-factor-challenge-expired');
        }

        $email = $admin->email();
        $ip = $command->ipAddress ?? '';
        $this->throttle->assertCredentialsNotLocked(AccountScope::Admin, $email, $ip);
        $admin->assertCanSignIn();

        $enrolling = ! $admin->hasTwoFactor();
        $step = null;
        $usedRecoveryCode = false;

        if ($command->code !== null) {
            $step = $this->authenticator->matchingStep((string) $admin->twoFactorSecret(), $command->code, $admin->twoFactorLastStep());
        } elseif ($command->recoveryCode !== null && ! $enrolling) {
            $usedRecoveryCode = $this->recoveryCodes->consume((int) $admin->id, $this->twoFactorTokens->hash($command->recoveryCode), $now);
        }

        if ($step === null && ! $usedRecoveryCode) {
            $this->throttle->recordCredentialFailure(AccountScope::Admin, $email, $ip);
            $this->events->dispatch([new SignInFailed(AccountScope::Admin, $admin->id, 'invalid-two-factor-code', $command->ipAddress, $command->userAgent, $now)]);

            throw new AuthenticationFailed('The verification code is incorrect.', 'invalid-two-factor-code');
        }

        $expiresAt = $now->modify(SignInAdmin::TOKEN_LIFETIME);

        [$token, $newRecoveryCodes] = $this->transactions->run(function () use ($admin, $challenge, $command, $step, $enrolling, $now, $expiresAt): array {
            $this->challenges->consume($challenge->id, $now);
            $codes = $this->finishSecondFactor($admin, $step, $enrolling, $now);

            $admin->recordSignIn($now);
            $this->admins->save($admin);

            return [$this->tokens->issueForAdmin((int) $admin->id, $command->deviceName, $expiresAt), $codes];
        });

        $this->throttle->clearCredentials(AccountScope::Admin, $email, $ip);

        $events = [new SignedIn(AccountScope::Admin, (int) $admin->id, $command->ipAddress, $command->userAgent, $now)];
        if ($enrolling) {
            $events[] = new TwoFactorEnabled((int) $admin->id, $command->ipAddress, $command->userAgent, $now);
        }
        if ($usedRecoveryCode) {
            $events[] = new RecoveryCodeUsed((int) $admin->id, $command->ipAddress, $command->userAgent, $now);
        }
        $this->events->dispatch($events);

        return new AdminSignInResult($token, $expiresAt, $admin, $newRecoveryCodes);
    }

    /**
     * Records the accepted time step, or confirms the second factor and creates the recovery codes when the admin was enrolling.
     *
     * @return list<string> the plain recovery codes when the factor was just confirmed, otherwise empty
     */
    private function finishSecondFactor(Admin $admin, ?int $step, bool $enrolling, DateTimeImmutable $now): array
    {
        if ($step === null) {
            return [];
        }

        if (! $enrolling) {
            $admin->recordTwoFactorStep($step);

            return [];
        }

        $admin->confirmTwoFactor($now, $step);
        $codes = $this->twoFactorTokens->recoveryCodes(self::RECOVERY_CODE_COUNT);
        $this->recoveryCodes->replace((int) $admin->id, array_map($this->twoFactorTokens->hash(...), $codes), $now);

        return $codes;
    }
}
