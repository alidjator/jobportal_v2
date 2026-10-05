<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRole;

/** One pending invitation in the company's list. `invitedBy` is the inviting member's name, or null when a platform admin invited. */
final readonly class PendingInvitationView
{
    public function __construct(
        public int $id,
        public string $email,
        public CompanyRole $role,
        public DateTimeImmutable $sentAt,
        public DateTimeImmutable $expiresAt,
        public ?string $invitedBy,
    ) {}
}
