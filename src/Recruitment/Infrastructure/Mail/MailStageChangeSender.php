<?php

namespace Src\Recruitment\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use LogicException;
use Src\Recruitment\Application\RecruitmentEmailTemplates;
use Src\Recruitment\Application\StageChangeEmailSender;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Applicant\ApplicantContact;

final readonly class MailStageChangeSender implements StageChangeEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(ApplicantContact $to, StageType $outcome, string $jobTitle, string $companyName): void
    {
        $key = RecruitmentEmailTemplates::keyFor($outcome) ?? throw new LogicException("No email is sent for the stage type {$outcome->value}.");

        $this->mailer->to($to->email)->locale($to->locale)->queue(new StageChangeMail($key, $to->name, $jobTitle, $companyName, $to->locale));
    }
}
