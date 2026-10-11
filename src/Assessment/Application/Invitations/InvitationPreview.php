<?php

namespace Src\Assessment\Application\Invitations;

use DateTimeImmutable;

/** What the accept form shows: the provider, the invited name and email, and when the invitation ends. */
final readonly class InvitationPreview
{
    public function __construct(
        public string $providerName,
        public string $name,
        public string $email,
        public DateTimeImmutable $expiresAt,
    ) {}
}
