<?php

namespace Src\Platform\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Email\RenderedEmail;
use Src\Shared\Infrastructure\Email\EssentialEmail;
use Src\Shared\Infrastructure\Queue\QueueName;

/** A message to the operations address. Always sent, even if the address was suppressed. The text comes from the email template of the given key, in English. Sent on the `default` queue, retried 3 times. */
final class OperationsMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    private ?RenderedEmail $rendered = null;

    /** @param  array<string, string>  $variables */
    public function __construct(private readonly string $templateKey, private readonly array $variables)
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

    public function headers(): Headers
    {
        return EssentialEmail::headers();
    }

    public function content(): Content
    {
        $rendered = $this->renderTemplate();

        return new Content(view: 'emails.rendered-html', text: 'emails.rendered-text', with: ['html' => $rendered->html, 'text' => $rendered->text]);
    }

    private function renderTemplate(): RenderedEmail
    {
        // Laravel calls envelope() and content() without arguments, so the renderer is taken from the container.
        return $this->rendered ??= Container::getInstance()->make(EmailTemplates::class)->render($this->templateKey, 'en', $this->variables);
    }
}
