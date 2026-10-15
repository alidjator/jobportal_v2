<?php

namespace Src\JobSeeker\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\JobSeeker\Application\Export\DataExportEmailSender;
use Src\JobSeeker\Application\Export\DataExportEmailTemplates;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Infrastructure\Queue\QueueName;

final readonly class MailDataExportSender implements DataExportEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, string $expiresAt): void
    {
        $this->mailer->to($to->email)->locale($to->locale)->queue(new TemplatedMail(
            DataExportEmailTemplates::READY,
            ['name' => $to->name, 'expires_at' => $expiresAt],
            $to->locale,
            QueueName::Default,
        ));
    }
}
