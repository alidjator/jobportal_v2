<?php

namespace Src\Identity\Application\Account;

/**
 * Erases the personal data of a user that Identity keeps outside the user row: social accounts, access tokens,
 * verification codes, and the IP addresses of terms acceptances (the acceptance rows stay as proof).
 */
interface UserDataEraser
{
    public function erase(int $userId): void;
}
