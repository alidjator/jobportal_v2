<?php

namespace Src\Assessment\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Assessment\Application\Notices\ProviderNoticeSender;

final readonly class MailProviderNoticeSender implements ProviderNoticeSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(string $email, string $locale, string $templateKey, array $variables): void
    {
        $this->mailer->to($email)->locale($locale)->queue(new TestMail($templateKey, $variables, $locale));
    }
}
