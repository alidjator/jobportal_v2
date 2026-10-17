<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Applicant\ApplicantContact;

/** Sends an email about an interview to the job seeker: the text comes from the email template with the given key. Implemented in Infrastructure. */
interface InterviewEmailSender
{
    /**
     * @param  array<string, string>  $variables  the template variables besides the recipient's name
     * @param  ?string  $calendar  the `.ics` file to attach (`interview.ics`), with its `METHOD` in `$calendarMethod`
     */
    public function send(ApplicantContact $to, string $templateKey, array $variables, ?string $calendar = null, string $calendarMethod = InterviewCalendar::REQUEST): void;
}
