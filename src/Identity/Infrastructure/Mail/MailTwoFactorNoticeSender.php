<?php

namespace Src\Identity\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Identity\Application\IdentityEmailTemplates;
use Src\Identity\Application\TwoFactor\TwoFactorNoticeSender;

final readonly class MailTwoFactorNoticeSender implements TwoFactorNoticeSender
{
    public function __construct(private Mailer $mailer) {}

    public function internalTenant(string $email, string $name, string $locale): void
    {
        $this->mailer->to($email)->locale($locale)->queue(new AccountNoticeMail(IdentityEmailTemplates::TENANT_INTERNAL, $locale, ['name' => $name]));
    }

    public function required(string $email, string $name, string $locale): void
    {
        $this->mailer->to($email)->locale($locale)->queue(new AccountNoticeMail(IdentityEmailTemplates::TWO_FACTOR_REQUIRED, $locale, ['name' => $name]));
    }
}
