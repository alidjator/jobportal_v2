<?php

namespace Src\Identity\Infrastructure\Mail;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Base of the emails that carry a verification code. Sent on the `critical` queue, retried 3 times with a growing delay. */
abstract class VerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $recipientName,
        private readonly string $code,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $sentAt,
    ) {
        $this->onQueue(QueueName::Critical->value);
    }

    /** The key of the subject line in `lang/*\/identity.php`. */
    abstract protected function subjectKey(): string;

    /** The markdown view of the body. */
    abstract protected function markdownView(): string;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->subjectKey()));
    }

    public function content(): Content
    {
        return new Content(
            markdown: $this->markdownView(),
            with: [
                'name' => $this->recipientName,
                'code' => $this->code,
                'minutes' => (int) round(($this->expiresAt->getTimestamp() - $this->sentAt->getTimestamp()) / 60),
            ],
        );
    }
}
