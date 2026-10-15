<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Domain\User;
use Src\Shared\Application\Company\MemberTwoFactorPolicy;

/**
 * Decides whether a sign-in with a right password may finish at once. A company member who must have a second factor (a company admin, or any member of a
 * company that requires it) or who has a confirmed one gets a challenge instead of a token, with the enrolment data when no factor is confirmed yet. Every
 * other account (a job seeker, a member who may do without) passes.
 */
final readonly class MemberTwoFactorGate
{
    public function __construct(
        private MemberTwoFactorPolicy $policy,
        private UserTwoFactors $factors,
        private IssueTwoFactorChallenge $challenge,
    ) {}

    public function challengeFor(User $user): ?TwoFactorChallengeIssued
    {
        if (! $user->isCompanyMember()) {
            return null;
        }

        $userId = (int) $user->id;

        if (! $this->policy->isRequired($userId) && ! ($this->factors->find($userId)?->isConfirmed() ?? false)) {
            return null;
        }

        return $this->challenge->execute($user);
    }
}
