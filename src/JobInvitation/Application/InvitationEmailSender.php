<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends the email of an invitation; the text comes from the `invitation.received` template. Implemented in Infrastructure. */
interface InvitationEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(ApplicantContact $to, array $variables): void;
}
