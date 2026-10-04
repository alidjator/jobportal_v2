<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;

/** Delivers a verification code to an email address. The plain code must never be logged or put in a domain event. */
interface VerificationCodeSender
{
    public function sendEmailVerification(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void;

    public function sendPasswordReset(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void;
}
