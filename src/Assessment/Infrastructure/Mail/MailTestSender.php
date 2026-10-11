<?php

namespace Src\Assessment\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Assessment\Application\Delivery\TestEmailSender;
use Src\Shared\Application\Applicant\ApplicantContact;

final readonly class MailTestSender implements TestEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, string $templateKey, array $variables): void
    {
        $this->mailer->to($to->email)->locale($to->locale)->queue(new TestMail($templateKey, ['name' => $to->name, ...$variables], $to->locale));
    }
}
