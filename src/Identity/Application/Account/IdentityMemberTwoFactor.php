<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\TwoFactor\TwoFactorNoticeSender;
use Src\Identity\Application\TwoFactor\UserTwoFactors;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\MemberTwoFactor;

/** Identity's side of the Company context's needs about the second factor of company members. */
final readonly class IdentityMemberTwoFactor implements MemberTwoFactor
{
    public function __construct(
        private UserRepository $users,
        private UserTwoFactors $factors,
        private AccessTokenIssuer $tokens,
        private TwoFactorNoticeSender $notices,
    ) {}

    public function confirmed(array $userIds): array
    {
        $confirmed = [];

        foreach ($userIds as $userId) {
            $confirmed[$userId] = $this->factors->find($userId)?->isConfirmed() ?? false;
        }

        return $confirmed;
    }

    public function requireFor(array $userIds): array
    {
        $ended = [];

        foreach ($this->users->findByIds($userIds) as $userId => $user) {
            if ($this->factors->find($userId)?->isConfirmed() ?? false) {
                continue;
            }

            $this->tokens->revokeAllForUser($userId);
            $this->notices->required($user->email(), $user->name(), $user->locale());
            $ended[] = $userId;
        }

        return $ended;
    }

    public function reset(int $userId): void
    {
        $this->factors->reset($userId);
        $this->tokens->revokeAllForUser($userId);
    }
}
