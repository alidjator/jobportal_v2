<?php

namespace Src\Newsletter\Infrastructure\Mail;

use Illuminate\Container\Container;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Src\Newsletter\Application\IssueMessage;
use Src\Shared\Application\Email\AdHocEmails;
use Src\Shared\Application\Email\RenderedEmail;

/**
 * One issue for one subscriber. It is not queued itself: it is sent from inside the queue job of its delivery, so a failure makes that job retry. It carries the
 * `List-Unsubscribe` headers (RFC 8058), so mail programs offer a one-click unsubscribe.
 */
final class NewsletterIssueMail extends Mailable
{
    private ?RenderedEmail $rendered = null;

    public function __construct(private readonly IssueMessage $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderMessage()->subject);
    }

    public function content(): Content
    {
        $rendered = $this->renderMessage();

        return new Content(view: 'emails.rendered-html', text: 'emails.rendered-text', with: ['html' => $rendered->html, 'text' => $rendered->text]);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->message->oneClickUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    private function renderMessage(): RenderedEmail
    {
        return $this->rendered ??= Container::getInstance()->make(AdHocEmails::class)->render($this->message->subject, $this->message->markdown);
    }
}
