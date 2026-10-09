<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends the reminder email for an unread conversation: the text comes from the email template with the given key. Implemented in Infrastructure. */
interface UnreadEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(ApplicantContact $to, string $templateKey, array $variables): void;
}
