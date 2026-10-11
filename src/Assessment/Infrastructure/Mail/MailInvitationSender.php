<?php

namespace Src\Assessment\Infrastructure\Mail;

use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Src\Assessment\Application\Invitations\InvitationSender;
use Src\Shared\Application\Clock;

final readonly class MailInvitationSender implements InvitationSender
{
    public function __construct(
        private Mailer $mailer,
        private Clock $clock,
    ) {}

    public function send(string $email, string $language, string $providerName, string $link, DateTimeImmutable $expiresAt): void
    {
        $this->mailer->to($email)->locale($language)->queue(
            new PsychologistInvitationMail($providerName, $link, $expiresAt, $this->clock->now(), $language),
        );
    }
}
