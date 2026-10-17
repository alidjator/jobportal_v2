<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends an email about their application to the job seeker (an offer, the receipt of the application): the text comes from the email template with the given key. Implemented in Infrastructure. */
interface ApplicantEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(ApplicantContact $to, string $templateKey, array $variables): void;
}
