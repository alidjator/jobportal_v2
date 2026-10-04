<?php

namespace Tests\Support;

use DateTimeImmutable;
use Src\Identity\Application\Auth\VerificationCodeSender;

/** Test double that remembers the plain codes, which the real sender hands straight to the mail queue. */
final class RecordingVerificationCodeSender implements VerificationCodeSender
{
    /** @var list<array{kind: string, email: string, name: string, locale: string, code: string, expiresAt: DateTimeImmutable}> */
    public array $sent = [];

    public function sendEmailVerification(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->record('email_verification', $email, $name, $locale, $code, $expiresAt);
    }

    public function sendPasswordReset(string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->record('password_reset', $email, $name, $locale, $code, $expiresAt);
    }

    public function lastCode(): string
    {
        return $this->sent[array_key_last($this->sent)]['code'];
    }

    private function record(string $kind, string $email, string $name, string $locale, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->sent[] = compact('kind', 'email', 'name', 'locale', 'code', 'expiresAt');
    }
}
