<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Domain\Events\UserTwoFactorEnabled;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The first right code confirms the second factor a company member started; the 10 recovery codes are shown only this once. */
final readonly class ConfirmMyTwoFactor
{
    public function __construct(
        private MyTwoFactorAccess $access,
        private UserCodeChecker $codes,
        private UserTwoFactors $factors,
        private UserRecoveryCodes $recoveryCodes,
        private TwoFactorTokens $twoFactorTokens,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return list<string> the plain recovery codes
     *
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     * @throws StateConflict `two-factor-not-started`
     * @throws BusinessRuleViolation `invalid-two-factor-code`
     * @throws TooManyAttempts
     */
    public function execute(Actor $actor, string $code, ?string $ipAddress, ?string $userAgent): array
    {
        $user = $this->access->user($actor);
        $userId = (int) $user->id;
        $step = $this->codes->check($user, $code, $ipAddress, confirmed: false);
        $now = $this->clock->now();
        $recoveryCodes = $this->twoFactorTokens->recoveryCodes(CompleteTwoFactorSignIn::RECOVERY_CODE_COUNT);

        $this->transactions->run(function () use ($userId, $step, $recoveryCodes, $now): void {
            $this->factors->confirm($userId, $step, $now);
            $this->recoveryCodes->replace($userId, array_map($this->twoFactorTokens->hash(...), $recoveryCodes), $now);
        });

        $this->events->dispatch([new UserTwoFactorEnabled($userId, $ipAddress, $userAgent, $now)]);

        return $recoveryCodes;
    }
}
