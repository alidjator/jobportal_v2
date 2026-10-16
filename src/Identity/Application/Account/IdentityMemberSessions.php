<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Application\TwoFactor\TwoFactorNoticeSender;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\MemberSessions;

/** Identity's side of the Organization context's needs about the sessions of company members. */
final readonly class IdentityMemberSessions implements MemberSessions
{
    public function __construct(private UserRepository $users, private AccessTokenIssuer $tokens, private TwoFactorNoticeSender $notices) {}

    public function endForInternalTenant(array $userIds): void
    {
        foreach ($this->users->findByIds($userIds) as $userId => $user) {
            $this->tokens->revokeAllForUser($userId);
            $this->notices->internalTenant($user->email(), $user->name(), $user->locale());
        }
    }
}
