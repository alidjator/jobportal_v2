<?php

namespace Src\JobInvitation\Application;

/** Sends the email of an invitation by email; the text comes from the `invitation.email_received` template. Implemented in Infrastructure. */
interface EmailInvitationEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name */
    public function send(string $email, string $name, string $language, array $variables): void;
}
