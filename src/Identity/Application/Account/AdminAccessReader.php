<?php

namespace Src\Identity\Application\Account;

/** Reads what an administrator may do. The RBAC package stays behind this interface (Infrastructure). */
interface AdminAccessReader
{
    public function forAdmin(int $adminId): AdminAccess;
}
