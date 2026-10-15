<?php

namespace Src\JobSeeker\Infrastructure\Mail;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Src\JobSeeker\Application\Reminders\ProfileReminderEmailSender;
use Src\JobSeeker\Application\Reminders\ProfileReminderEmailTemplates;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Queues the reminder. The link is the client page in `app.profile_url` (env `JOB_SEEKER_PROFILE_URL`); without it the link is empty. */
final readonly class MailProfileReminderSender implements ProfileReminderEmailSender
{
    public function __construct(private Mailer $mailer, private Repository $config) {}

    public function send(ApplicantContact $to, array $variables): void
    {
        $link = $this->config->get('app.profile_url');

        $this->mailer->to($to->email)->locale($to->locale)->queue(new TemplatedMail(
            ProfileReminderEmailTemplates::PROFILE_REMINDER,
            ['name' => $to->name, 'profile_link' => is_string($link) ? $link : '', ...$variables],
            $to->locale,
            QueueName::Bulk,
        ));
    }
}
