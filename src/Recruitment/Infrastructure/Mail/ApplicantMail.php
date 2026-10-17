<?php

namespace Src\Recruitment\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Email\RenderedEmail;
use Src\Shared\Infrastructure\Email\FramesCompanyEmail;
use Src\Shared\Infrastructure\Queue\QueueName;

/** The email that tells a job seeker about their application: a job offer, or that the application was received. The text comes from the email template, rendered when the mail is sent, in the recipient's language. Sent on the `default` queue, retried 3 times with a growing delay. */
final class ApplicantMail extends Mailable implements ShouldQueue
{
    use FramesCompanyEmail;
    use Queueable;

    public int $tries = 3;

    private ?RenderedEmail $rendered = null;

    /** @param  array<string, string>  $variables */
    public function __construct(private readonly string $templateKey, private readonly array $variables, private readonly string $language)
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

        return new Content(view: 'emails.rendered-html', text: 'emails.rendered-text', with: ['html' => $rendered->html, 'text' => $rendered->text, 'frame' => $this->companyFrame($this->variables, $this->language)]);
    }

    private function renderTemplate(): RenderedEmail
    {
        // Laravel calls envelope() and content() without arguments, so the renderer is taken from the container.
        return $this->rendered ??= Container::getInstance()->make(EmailTemplates::class)->render($this->templateKey, $this->language, $this->variables);
    }
}
