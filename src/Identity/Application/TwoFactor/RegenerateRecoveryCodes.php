<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\AdminRepository;
use Src\Identity\Domain\Events\RecoveryCodesRegenerated;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Replaces the recovery codes of the signed-in admin (the old ones stop working). Needs a current code of the authenticator app. */
final readonly class RegenerateRecoveryCodes
{
    public function __construct(
        private AdminRepository $admins,
        private RecoveryCodes $recoveryCodes,
        private TwoFactorAuthenticator $authenticator,
        private TwoFactorTokens $twoFactorTokens,
        private SignInThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return list<string> the new plain recovery codes, shown only once
     *
     * @throws EntityNotFound
     * @throws StateConflict `two-factor-not-enabled` when the admin has no confirmed second factor
     * @throws BusinessRuleViolation `invalid-two-factor-code` for a wrong code
     * @throws TooManyAttempts
     */
    public function execute(Actor $actor, string $code, ?string $ipAddress, ?string $userAgent): array
    {
        $admin = $this->admins->findById((int) $actor->id) ?? throw EntityNotFound::of('Admin', $actor->id);

        if (! $admin->hasTwoFactor()) {
            throw new StateConflict('This administrator has no second factor yet.', 'two-factor-not-enabled');
        }

        $ip = $ipAddress ?? '';
        $this->throttle->assertCredentialsNotLocked(AccountScope::Admin, $admin->email(), $ip);

        $step = $this->authenticator->matchingStep((string) $admin->twoFactorSecret(), $code, $admin->twoFactorLastStep());

        if ($step === null) {
            $this->throttle->recordCredentialFailure(AccountScope::Admin, $admin->email(), $ip);

            throw new BusinessRuleViolation('The verification code is incorrect.', 'invalid-two-factor-code');
        }

        $now = $this->clock->now();
        $codes = $this->twoFactorTokens->recoveryCodes(CompleteAdminSignIn::RECOVERY_CODE_COUNT);

        $this->transactions->run(function () use ($admin, $step, $codes, $now): void {
            $admin->recordTwoFactorStep($step);
            $this->admins->save($admin);
            $this->recoveryCodes->replace((int) $admin->id, array_map($this->twoFactorTokens->hash(...), $codes), $now);
        });

        $this->throttle->clearCredentials(AccountScope::Admin, $admin->email(), $ip);
        $this->events->dispatch([new RecoveryCodesRegenerated((int) $admin->id, $ipAddress, $userAgent, $now)]);

        return $codes;
    }
}
