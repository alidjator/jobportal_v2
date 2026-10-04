<?php

namespace Src\Identity\Domain;

use Src\Shared\Domain\Exceptions\AccessDenied;

enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /** @throws AccessDenied when the account may not sign in */
    public function assertCanSignIn(): void
    {
        match ($this) {
            self::Active => null,
            self::Suspended => throw new AccessDenied('This account has been suspended.', 'account-suspended'),
            self::Deactivated => throw new AccessDenied('This account has been deactivated.', 'account-deactivated'),
        };
    }
}
