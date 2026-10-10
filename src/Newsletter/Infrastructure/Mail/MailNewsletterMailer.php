<?php

namespace Src\Newsletter\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Newsletter\Application\IssueMessage;
use Src\Newsletter\Application\NewsletterMailer;

/** Sends an issue at once through the application mailer (its caller is a queue job, so a failure retries the job). */
final readonly class MailNewsletterMailer implements NewsletterMailer
{
    public function __construct(private Mailer $mailer) {}

    public function send(IssueMessage $message): void
    {
        $this->mailer->to($message->email)->send(new NewsletterIssueMail($message));
    }
}
