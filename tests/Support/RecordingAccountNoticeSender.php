<?php

namespace Tests\Support;

use DateTimeImmutable;
use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Domain\UserType;

/** Test double that remembers the account notices, with the plain codes the real sender hands straight to the mail queue. */
final class RecordingAccountNoticeSender implements AccountNoticeSender
{
    /** @var list<array{kind: string, to: string, name: string, locale: string, newEmail: ?string, code: ?string, audience: ?string}> */
    public array $sent = [];

    public function emailChangeCode(string $to, string $name, string $locale, string $newEmail, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->record('code', $to, $name, $locale, $newEmail, $code);
    }

    public function emailChangeTaken(string $to, string $name, string $locale): void
    {
        $this->record('taken', $to, $name, $locale);
    }

    public function emailChanged(string $oldEmail, string $name, string $locale, string $newEmail): void
    {
        $this->record('changed', $oldEmail, $name, $locale, $newEmail);
    }

    public function emailCorrectedOld(string $oldEmail, string $name, string $locale, string $newEmail): void
    {
        $this->record('corrected_old', $oldEmail, $name, $locale, $newEmail);
    }

    public function emailCorrectedNew(string $newEmail, string $name, string $locale, UserType $audience): void
    {
        $this->record('corrected_new', $newEmail, $name, $locale, null, null, $audience->value);
    }

    public function twoFactorReset(string $email, string $name, string $locale): void
    {
        $this->record('two_factor_reset', $email, $name, $locale);
    }

    /** The notices of one kind, in the order they were sent. @return list<array{kind: string, to: string, name: string, locale: string, newEmail: ?string, code: ?string, audience: ?string}> */
    public function of(string $kind): array
    {
        return array_values(array_filter($this->sent, static fn (array $notice): bool => $notice['kind'] === $kind));
    }

    /** The code sent to this address by the latest code notice. */
    public function codeFor(string $to): string
    {
        $codes = array_values(array_filter($this->of('code'), static fn (array $notice): bool => $notice['to'] === $to));

        return (string) $codes[array_key_last($codes)]['code'];
    }

    private function record(string $kind, string $to, string $name, string $locale, ?string $newEmail = null, ?string $code = null, ?string $audience = null): void
    {
        $this->sent[] = compact('kind', 'to', 'name', 'locale', 'newEmail', 'code', 'audience');
    }
}
