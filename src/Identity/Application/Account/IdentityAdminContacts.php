<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\AdminRepository;
use Src\Shared\Application\Accounts\AdminContacts;

/** Gives other contexts the email of an admin account. */
final readonly class IdentityAdminContacts implements AdminContacts
{
    public function __construct(private AdminRepository $admins) {}

    public function emailOf(int $adminId): ?string
    {
        return $this->admins->findById($adminId)?->email();
    }
}
