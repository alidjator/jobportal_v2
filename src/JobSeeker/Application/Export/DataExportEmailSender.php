<?php

namespace Src\JobSeeker\Application\Export;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends the email that says the copy of the personal data is ready. Implemented in Infrastructure. */
interface DataExportEmailSender
{
    public function send(ApplicantContact $to, string $expiresAt): void;
}
