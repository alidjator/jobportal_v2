<?php

namespace Src\JobAlert\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\JobAlert\Application\AlertEmailSender;
use Src\Shared\Application\Applicant\ApplicantContact;

final readonly class MailAlertSender implements AlertEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, array $variables): void
    {
        $this->mailer->to($to->email)->locale($to->locale)->queue(new AlertDigestMail(['name' => $to->name, ...$variables], $to->locale));
    }
}
