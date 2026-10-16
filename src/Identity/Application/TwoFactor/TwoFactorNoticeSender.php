<?php

namespace Src\Identity\Application\TwoFactor;

/** Emails a user about their second factor. Sent in the recipient's own language. */
interface TwoFactorNoticeSender
{
    /** Two-step verification became required for the account and it has none yet. */
    public function required(string $email, string $name, string $locale): void;

    /** The account's company became an internal tenant of an ERP group, so its sessions ended and the internal sign-in rules apply. */
    public function internalTenant(string $email, string $name, string $locale): void;
}
