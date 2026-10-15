<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company member starts to switch the second factor on: a fresh secret (a pending one is replaced) with its link and QR code. `ConfirmMyTwoFactor` finishes it. */
final readonly class BeginMyTwoFactorEnrolment
{
    public function __construct(
        private MyTwoFactorAccess $access,
        private UserTwoFactors $factors,
        private TwoFactorAuthenticator $authenticator,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`
     * @throws EntityNotFound
     * @throws StateConflict `two-factor-already-enabled`
     */
    public function execute(Actor $actor): TwoFactorEnrolment
    {
        $user = $this->access->user($actor);
        $userId = (int) $user->id;

        if ($this->factors->find($userId)?->isConfirmed() ?? false) {
            throw new StateConflict('The second factor is already switched on.', 'two-factor-already-enabled');
        }

        $secret = $this->authenticator->generateSecret();
        $this->factors->begin($userId, $secret, $this->clock->now());

        $url = $this->authenticator->otpauthUrl($user->email(), $secret);

        return new TwoFactorEnrolment($secret, $url, $this->authenticator->qrCode($url));
    }
}
