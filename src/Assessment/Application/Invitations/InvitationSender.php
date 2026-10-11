<?php

namespace Src\Assessment\Application\Invitations;

use DateTimeImmutable;

interface InvitationSender
{
    /** Queues the invitation email in the given language (the request language: the invitee has no account yet). */
    public function send(string $email, string $language, string $providerName, string $link, DateTimeImmutable $expiresAt): void;
}
