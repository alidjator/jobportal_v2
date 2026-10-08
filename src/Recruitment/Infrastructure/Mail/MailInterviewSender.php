<?php

namespace Src\Recruitment\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Recruitment\Application\InterviewEmailSender;
use Src\Shared\Application\Applicant\ApplicantContact;

final readonly class MailInterviewSender implements InterviewEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, string $templateKey, array $variables): void
    {
        $this->mailer->to($to->email)->locale($to->locale)->queue(new InterviewMail($templateKey, ['name' => $to->name, ...$variables], $to->locale));
    }
}
