<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Domain\Events\UserRecoveryCodesRegenerated;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Replaces the recovery codes of a company member (the old ones stop working). Needs a current code of the authenticator app. */
final readonly class RegenerateMyRecoveryCodes
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
     * @return list<string> the new plain recovery codes, shown only once
     *
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     * @throws StateConflict `two-factor-not-enabled`
     * @throws BusinessRuleViolation `invalid-two-factor-code`
     * @throws TooManyAttempts
     */
    public function execute(Actor $actor, string $code, ?string $ipAddress, ?string $userAgent): array
    {
        $user = $this->access->user($actor);
        $userId = (int) $user->id;
        $step = $this->codes->check($user, $code, $ipAddress);
        $now = $this->clock->now();
        $codes = $this->twoFactorTokens->recoveryCodes(CompleteTwoFactorSignIn::RECOVERY_CODE_COUNT);

        $this->transactions->run(function () use ($userId, $step, $codes, $now): void {
            $this->factors->recordStep($userId, $step, $now);
            $this->recoveryCodes->replace($userId, array_map($this->twoFactorTokens->hash(...), $codes), $now);
        });

        $this->events->dispatch([new UserRecoveryCodesRegenerated($userId, $ipAddress, $userAgent, $now)]);

        return $codes;
    }
}
