<?php

namespace Src\Identity\Infrastructure\Mail;

use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Shared\Application\Clock;

/** Emails the code in the recipient's own language (the locale of their account, never a request header). */
final readonly class MailVerificationCodeSender implements VerificationCodeSender
{
    public function __construct(
        private Mailer $mailer,
        private Clock $clock,
    ) {}

    public function sendEmailVerification(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->queue($email, $locale, new EmailVerificationMail($name, $code, $expiresAt, $this->clock->now()));
    }

    public function sendPasswordReset(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->queue($email, $locale, new PasswordResetMail($name, $code, $expiresAt, $this->clock->now()));
    }

    private function queue(string $email, string $locale, VerificationCodeMail $mail): void
    {
        $this->mailer->to($email)->locale($locale)->queue($mail);
    }
}
