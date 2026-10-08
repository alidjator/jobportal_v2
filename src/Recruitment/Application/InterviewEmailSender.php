<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends an email about an interview to the job seeker: the text comes from the email template with the given key. Implemented in Infrastructure. */
interface InterviewEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(ApplicantContact $to, string $templateKey, array $variables): void;
}
