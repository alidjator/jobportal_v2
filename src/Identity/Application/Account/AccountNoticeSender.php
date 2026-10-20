<?php

namespace Src\Identity\Application\Account;

use DateTimeImmutable;
use Src\Identity\Domain\UserType;

/** Emails an account holder about their sign-in email and second factor, in their own language. The plain code must never be logged or put in a domain event. */
interface AccountNoticeSender
{
    /** The code that confirms the change: sent to the new address and, for an account without a password, to the current one too. */
    public function emailChangeCode(string $to, string $name, string $locale, string $newEmail, string $code, DateTimeImmutable $expiresAt): void;

    /** Someone asked to use this address, which belongs to an account already. Holds no code. */
    public function emailChangeTaken(string $to, string $name, string $locale): void;

    /** To the old address once the owner changed the email. */
    public function emailChanged(string $oldEmail, string $name, string $locale, string $newEmail): void;

    /** To the old address once the platform team corrected the email. */
    public function emailCorrectedOld(string $oldEmail, string $name, string $locale, string $newEmail): void;

    /** To the new address once the platform team corrected the email: where to set the password. */
    public function emailCorrectedNew(string $newEmail, string $name, string $locale, UserType $audience): void;

    /** The platform team removed the second factor. */
    public function twoFactorReset(string $email, string $name, string $locale): void;
}
