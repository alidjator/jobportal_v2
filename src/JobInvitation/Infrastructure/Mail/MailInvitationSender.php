<?php

namespace Src\JobInvitation\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\JobInvitation\Application\InvitationEmailSender;
use Src\Shared\Application\Applicant\ApplicantContact;

final readonly class MailInvitationSender implements InvitationEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, array $variables): void
    {
        $this->mailer->to($to->email)->locale($to->locale)->queue(new InvitationMail(['name' => $to->name, ...$variables], $to->locale));
    }
}
