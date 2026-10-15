<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\User;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Checks a code of the authenticator app against the second factor of a signed-in user. A wrong code counts in the sign-in throttle of the account. */
final readonly class UserCodeChecker
{
    public function __construct(
        private UserTwoFactors $factors,
        private TwoFactorAuthenticator $authenticator,
        private SignInThrottle $throttle,
    ) {}

    /**
     * @param  bool  $confirmed  true when the factor must be confirmed already; false when the code is the one that confirms it
     * @return int the accepted time step, to record so the code cannot be used twice
     *
     * @throws StateConflict `two-factor-not-enabled` (confirmed wanted, none) or `two-factor-not-started` (enrolment wanted, none or done)
     * @throws BusinessRuleViolation `invalid-two-factor-code`
     * @throws TooManyAttempts
     */
    public function check(User $user, string $code, ?string $ipAddress, bool $confirmed = true): int
    {
        $factor = $this->factors->find((int) $user->id);

        if ($confirmed && ! ($factor?->isConfirmed() ?? false)) {
            throw new StateConflict('This account has no second factor yet.', 'two-factor-not-enabled');
        }

        if (! $confirmed && ($factor === null || $factor->isConfirmed())) {
            throw new StateConflict('No enrolment is waiting for a code. Start one first.', 'two-factor-not-started');
        }

        $ip = $ipAddress ?? '';
        $this->throttle->assertCredentialsNotLocked(AccountScope::User, $user->email(), $ip);

        $step = $this->authenticator->matchingStep($factor->secret, $code, $factor->lastStep);

        if ($step === null) {
            $this->throttle->recordCredentialFailure(AccountScope::User, $user->email(), $ip);

            throw new BusinessRuleViolation('The verification code is incorrect.', 'invalid-two-factor-code');
        }

        $this->throttle->clearCredentials(AccountScope::User, $user->email(), $ip);

        return $step;
    }
}
