<?php

namespace Src\Identity\Application\TwoFactor;

/** Emails a user about their second factor. Sent in the recipient's own language. */
interface TwoFactorNoticeSender
{
    /** Two-step verification became required for the account and it has none yet. */
    public function required(string $email, string $name, string $locale): void;
}
