<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\PasswordHasher;
use Src\Identity\Application\Auth\SignInThrottle;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\UserTwoFactorDisabled;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\MemberTwoFactorPolicy;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A company member switches the second factor off. Not when it is required of them (a company admin, or a company that requires it). Needs the password and a current code. */
final readonly class DisableMyTwoFactor
{
    public function __construct(
        private MyTwoFactorAccess $access,
        private MemberTwoFactorPolicy $policy,
        private PasswordHasher $hasher,
        private UserCodeChecker $codes,
        private UserTwoFactors $factors,
        private SignInThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     * @throws StateConflict `two-factor-required` or `two-factor-not-enabled`
     * @throws BusinessRuleViolation `invalid-password` or `invalid-two-factor-code`
     * @throws TooManyAttempts
     */
    public function execute(Actor $actor, string $password, string $code, ?string $ipAddress, ?string $userAgent): void
    {
        $user = $this->access->user($actor);
        $userId = (int) $user->id;

        if ($this->policy->isRequired($userId)) {
            throw new StateConflict('The second factor is required for this account and cannot be switched off.', 'two-factor-required');
        }

        $ip = $ipAddress ?? '';
        $this->throttle->assertCredentialsNotLocked(AccountScope::User, $user->email(), $ip);

        if (! $this->hasher->check($password, $user->passwordHash())) {
            $this->throttle->recordCredentialFailure(AccountScope::User, $user->email(), $ip);

            throw new BusinessRuleViolation('The password is incorrect.', 'invalid-password');
        }

        $this->codes->check($user, $code, $ipAddress);

        $this->transactions->run(fn () => $this->factors->reset($userId));
        $this->events->dispatch([new UserTwoFactorDisabled($userId, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
