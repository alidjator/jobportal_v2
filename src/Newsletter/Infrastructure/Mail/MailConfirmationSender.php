<?php

namespace Src\Newsletter\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Newsletter\Application\ConfirmationSender;

/** Queues the confirmation email. */
final readonly class MailConfirmationSender implements ConfirmationSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(string $email, string $locale, string $link): void
    {
        $this->mailer->to($email)->send(new ConfirmationMail($link, $locale));
    }
}
