<?php

namespace Src\Recruitment\Application;

/** Sends an email to a member of the company's team: the text comes from the email template with the given key. Implemented in Infrastructure. */
interface TeamEmailSender
{
    /**
     * @param  array<string, string>  $variables  the template variables, the recipient's `name` included
     * @param  ?string  $calendar  the `.ics` file to attach (`interview.ics`), with its `METHOD` in `$calendarMethod`
     */
    public function send(string $email, string $locale, string $templateKey, array $variables, ?string $calendar = null, string $calendarMethod = InterviewCalendar::REQUEST): void;
}
