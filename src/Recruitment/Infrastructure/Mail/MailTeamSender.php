<?php

namespace Src\Recruitment\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Recruitment\Application\InterviewCalendar;
use Src\Recruitment\Application\TeamEmailSender;

final readonly class MailTeamSender implements TeamEmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(string $email, string $locale, string $templateKey, array $variables, ?string $calendar = null, string $calendarMethod = InterviewCalendar::REQUEST): void
    {
        $this->mailer->to($email)->locale($locale)->queue(new InterviewMail($templateKey, $variables, $locale, $calendar, $calendarMethod));
    }
}
