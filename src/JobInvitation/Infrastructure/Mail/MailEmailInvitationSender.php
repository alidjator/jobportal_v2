<?php

namespace Src\JobInvitation\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\JobInvitation\Application\EmailInvitationEmailSender;

final readonly class MailEmailInvitationSender implements EmailInvitationEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(string $email, string $name, string $language, array $variables): void
    {
        $this->mailer->to($email)->locale($language)->queue(new EmailInvitationMail(['name' => $name, ...$variables], $language));
    }
}
