<?php

namespace Src\Assessment\Infrastructure\Mail;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Email\RenderedEmail;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * The invitation to become a psychologist of a provider. The text comes from the `assessment.psychologist_invitation` email template (an
 * admin's override, or the default), rendered when the mail is sent. Sent on the `critical` queue, retried 3 times with a growing delay.
 */
final class PsychologistInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    private ?RenderedEmail $rendered = null;

    public function __construct(
        private readonly string $providerName,
        private readonly string $link,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $sentAt,
        private readonly string $language,
    ) {
        $this->onQueue(QueueName::Critical->value);
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
        // Laravel calls envelope() and content() without arguments, so the renderer is taken from the container.
        return $this->rendered ??= Container::getInstance()->make(EmailTemplates::class)->render(AssessmentEmailTemplates::PSYCHOLOGIST_INVITATION, $this->language, [
            'provider' => $this->providerName,
            'link' => $this->link,
            'days' => (string) (int) round(($this->expiresAt->getTimestamp() - $this->sentAt->getTimestamp()) / 86400),
        ]);
    }
}
