<?php

namespace Src\Company\Application\Invitations;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRole;

interface InvitationSender
{
    /** Queues the invitation email in the given language (the request language: the invitee has no account yet). */
    public function send(string $email, string $language, string $companyName, CompanyRole $role, string $link, DateTimeImmutable $expiresAt): void;
}
