<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\Admin;

final readonly class CurrentAdmin
{
    public function __construct(
        public Admin $admin,
        public AdminAccess $access,
    ) {}
}
