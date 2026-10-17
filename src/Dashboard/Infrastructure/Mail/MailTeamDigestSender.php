<?php

namespace Src\Dashboard\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Dashboard\Application\TeamDigestSender;

final readonly class MailTeamDigestSender implements TeamDigestSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(string $email, string $language, array $variables): void
    {
        $this->mailer->to($email)->locale($language)->queue(new TeamDigestMail($language, $variables));
    }
}
