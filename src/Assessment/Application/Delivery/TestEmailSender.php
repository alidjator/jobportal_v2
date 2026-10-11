<?php

namespace Src\Assessment\Application\Delivery;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends an email about a test to the candidate: the text comes from the email template with the given key. Implemented in Infrastructure. */
interface TestEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(ApplicantContact $to, string $templateKey, array $variables): void;
}
