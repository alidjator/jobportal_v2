<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\MemberTwoFactorPolicy;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class GetMyTwoFactor
{
    public function __construct(
        private MyTwoFactorAccess $access,
        private UserTwoFactors $factors,
        private UserRecoveryCodes $recoveryCodes,
        private MemberTwoFactorPolicy $policy,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor): MyTwoFactorStatus
    {
        $userId = (int) $this->access->account($actor)->id;
        $enabled = $this->factors->find($userId)?->isConfirmed() ?? false;

        return new MyTwoFactorStatus($enabled, $this->required($actor, $userId), $enabled ? $this->recoveryCodes->remaining($userId) : 0);
    }

    /** A psychologist always needs the second factor (it is enrolled at the first sign-in); a company member needs it by the policy of their company. */
    private function required(Actor $actor, int $userId): bool
    {
        return $actor->kind === ActorKind::Psychologist || $this->policy->isRequired($userId);
    }
}
