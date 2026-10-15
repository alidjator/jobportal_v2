<?php

namespace Src\JobSeeker\Application\Reminders;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends the profile reminder email; the text comes from the `job_seeker.profile_reminder` template. Implemented in Infrastructure. */
interface ProfileReminderEmailSender
{
    /** @param  array<string, string>  $variables  the template variables besides the recipient's name and the link to the profile */
    public function send(ApplicantContact $to, array $variables): void;
}
