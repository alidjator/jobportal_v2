<?php

namespace Src\Company\Infrastructure\Mail;

use DateTimeImmutable;
use Illuminate\Contracts\Mail\Mailer;
use Src\Company\Application\Invitations\InvitationSender;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Clock;

final readonly class MailInvitationSender implements InvitationSender
{
    public function __construct(
        private Mailer $mailer,
        private Clock $clock,
    ) {}

    public function send(string $email, string $language, string $companyName, CompanyRole $role, string $link, DateTimeImmutable $expiresAt): void
    {
        $this->mailer->to($email)->locale($language)->queue(
            new InvitationMail($companyName, $role, $link, $expiresAt, $this->clock->now(), $language),
        );
    }
}
