<?php

namespace Src\Identity\Infrastructure\Mail;

use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Activation\ActivationLinks;
use Src\Identity\Application\IdentityEmailTemplates;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;

/** Queues the account notices as essential emails (they pass the suppression listener: the person needs them to use or protect the account). */
final readonly class MailAccountNoticeSender implements AccountNoticeSender
{
    public function __construct(
        private Mailer $mailer,
        private ActivationLinks $links,
        private Settings $settings,
        private Clock $clock,
    ) {}

    public function emailChangeCode(string $to, string $name, string $locale, string $newEmail, string $code, DateTimeImmutable $expiresAt): void
    {
        $minutes = max(1, (int) ceil(($expiresAt->getTimestamp() - $this->clock->now()->getTimestamp()) / 60));

        $this->send($to, $locale, IdentityEmailTemplates::EMAIL_CHANGE_CODE, ['name' => $name, 'new_email' => $newEmail, 'code' => $code, 'minutes' => (string) $minutes]);
    }

    public function emailChangeTaken(string $to, string $name, string $locale): void
    {
        $this->send($to, $locale, IdentityEmailTemplates::EMAIL_CHANGE_TAKEN, ['name' => $name]);
    }

    public function emailChanged(string $oldEmail, string $name, string $locale, string $newEmail): void
    {
        $this->send($oldEmail, $locale, IdentityEmailTemplates::EMAIL_CHANGED, ['name' => $name, 'new_email' => $newEmail, 'support_contact' => $this->supportContact()]);
    }

    public function emailCorrectedOld(string $oldEmail, string $name, string $locale, string $newEmail): void
    {
        $this->send($oldEmail, $locale, IdentityEmailTemplates::EMAIL_CORRECTED_OLD, ['name' => $name, 'new_email' => $newEmail, 'support_contact' => $this->supportContact()]);
    }

    public function emailCorrectedNew(string $newEmail, string $name, string $locale, UserType $audience): void
    {
        $this->send($newEmail, $locale, IdentityEmailTemplates::EMAIL_CORRECTED_NEW, [
            'name' => $name, 'activation_url' => (string) $this->links->activationUrl($audience), 'support_contact' => $this->supportContact(),
        ]);
    }

    public function twoFactorReset(string $email, string $name, string $locale): void
    {
        $this->send($email, $locale, IdentityEmailTemplates::TWO_FACTOR_RESET, ['name' => $name, 'support_contact' => $this->supportContact()]);
    }

    /** @param  array<string, string>  $variables */
    private function send(string $to, string $locale, string $template, array $variables): void
    {
        $this->mailer->to($to)->locale($locale)->queue(new AccountNoticeMail($template, $locale, $variables));
    }

    private function supportContact(): string
    {
        $support = $this->settings->text('platform.support_email');
        $whatsapp = $this->settings->text('platform.support_whatsapp');

        return $whatsapp === '' ? $support : "{$support} / WhatsApp {$whatsapp}";
    }
}
