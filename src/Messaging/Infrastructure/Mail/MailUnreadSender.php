<?php

namespace Src\Messaging\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Messaging\Application\UnreadEmailSender;
use Src\Shared\Application\Applicant\ApplicantContact;

final readonly class MailUnreadSender implements UnreadEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, string $templateKey, array $variables): void
    {
        $this->mailer->to($to->email)->locale($to->locale)->queue(new UnreadMessageMail($templateKey, ['name' => $to->name, ...$variables], $to->locale));
    }
}
