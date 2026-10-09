<?php

namespace Src\JobAlert\Application;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends the email of a job alert; the text comes from the `jobalert.digest` template. Implemented in Infrastructure. */
interface AlertEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(ApplicantContact $to, array $variables): void;
}
