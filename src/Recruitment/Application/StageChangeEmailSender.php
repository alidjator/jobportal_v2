<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends the email that tells an applicant their application entered a stage (the text comes from the email template of the outcome). Implemented in Infrastructure. */
interface StageChangeEmailSender
{
    public function send(ApplicantContact $to, StageType $outcome, string $jobTitle, string $companyName): void;
}
