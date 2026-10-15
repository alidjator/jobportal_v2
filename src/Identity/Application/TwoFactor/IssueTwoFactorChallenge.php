<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Domain\User;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;

/**
 * Issues the 5-minute, single-use challenge that sits between the password step and the code step of a sign-in that needs a second factor. An account
 * without a confirmed factor also gets a fresh secret to enrol one.
 */
final readonly class IssueTwoFactorChallenge
{
    public const string CHALLENGE_LIFETIME = '+5 minutes';

    public function __construct(
        private TwoFactorAuthenticator $authenticator,
        private TwoFactorTokens $twoFactorTokens,
        private UserTwoFactors $factors,
        private UserTwoFactorChallenges $challenges,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function execute(User $user): TwoFactorChallengeIssued
    {
        $now = $this->clock->now();
        $expiresAt = $now->modify(self::CHALLENGE_LIFETIME);
        $token = $this->twoFactorTokens->challengeToken();
        $userId = (int) $user->id;

        return $this->transactions->run(function () use ($user, $userId, $token, $expiresAt, $now): TwoFactorChallengeIssued {
            $enrolment = null;

            if (! ($this->factors->find($userId)?->isConfirmed() ?? false)) {
                $secret = $this->authenticator->generateSecret();
                $this->factors->begin($userId, $secret, $now);

                $url = $this->authenticator->otpauthUrl($user->email(), $secret);
                $enrolment = new TwoFactorEnrolment($secret, $url, $this->authenticator->qrCode($url));
            }

            $this->challenges->issue($userId, $this->twoFactorTokens->hash($token), $expiresAt, $now);

            return new TwoFactorChallengeIssued($token, $expiresAt, $enrolment);
        });
    }
}
