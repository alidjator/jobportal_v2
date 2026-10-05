<?php

namespace Src\Company\Application\Invitations;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRole;

/** What the invitee sees before accepting. */
final readonly class InvitationPreview
{
    public function __construct(
        public string $companyName,
        public string $email,
        public CompanyRole $role,
        public DateTimeImmutable $expiresAt,
    ) {}
}
