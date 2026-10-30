<?php

namespace Src\JobInvitation\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Src\JobInvitation\Application\InvitationEmailTemplates;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Email\RenderedEmail;
use Src\Shared\Infrastructure\Queue\QueueName;

/** The email of an invitation by email. The text comes from the `invitation.email_received` template, rendered when the mail is sent. Sent on the `default` queue, retried 3 times with a growing delay. */
final class EmailInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    private ?RenderedEmail $rendered = null;

    /** @param  array<string, string>  $variables */
    public function __construct(private readonly array $variables, private readonly string $language)
    {
        $this->onQueue(QueueName::Default->value);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderTemplate()->subject);
    }

    public function content(): Content
    {
        $rendered = $this->renderTemplate();

        return new Content(view: 'emails.rendered-html', text: 'emails.rendered-text', with: ['html' => $rendered->html, 'text' => $rendered->text]);
    }

    private function renderTemplate(): RenderedEmail
    {
        return $this->rendered ??= Container::getInstance()->make(EmailTemplates::class)->render(InvitationEmailTemplates::EMAIL_RECEIVED, $this->language, $this->variables);
    }
}
