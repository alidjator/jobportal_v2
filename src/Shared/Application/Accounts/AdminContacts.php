<?php

namespace Src\Shared\Application\Accounts;

/** The contact of an admin account. Provided by Identity. */
interface AdminContacts
{
    /** The admin's email, or null when there is no such admin. */
    public function emailOf(int $adminId): ?string;
}
