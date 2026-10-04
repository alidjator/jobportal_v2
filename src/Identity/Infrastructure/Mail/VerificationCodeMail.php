<?php

namespace Src\Identity\Infrastructure\Mail;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Email\RenderedEmail;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Base of the emails that carry a verification code. The text comes from the email template (an admin's override, or the default),
 * rendered when the mail is sent, in the recipient's language. Sent on the `critical` queue, retried 3 times with a growing delay.
 */
abstract class VerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    private ?RenderedEmail $rendered = null;

    public function __construct(
        private readonly string $recipientName,
        private readonly string $code,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $sentAt,
        private readonly string $language,
    ) {
        $this->onQueue(QueueName::Critical->value);
    }

    /** The key of the email template to render. */
    abstract protected function templateKey(): string;

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
        // Laravel calls envelope() and content() without arguments, so the renderer is taken from the container.
        return $this->rendered ??= Container::getInstance()->make(EmailTemplates::class)->render($this->templateKey(), $this->language, [
            'name' => $this->recipientName,
            'code' => $this->code,
            'minutes' => (string) (int) round(($this->expiresAt->getTimestamp() - $this->sentAt->getTimestamp()) / 60),
        ]);
    }
}
