<?php

namespace Src\Company\Application\Team;

use DateTimeImmutable;
use Src\Company\Domain\CompanyRole;

/** One pending invitation in the company's list. `invitedBy` is the inviting member's name, or null when a platform admin invited. */
final readonly class PendingInvitationView
{
    /** @param  list<string>  $permissions  every permission for a company admin */
    public function __construct(
        public int $id,
        public string $email,
        public ?string $name,
        public CompanyRole $role,
        public array $permissions,
        public DateTimeImmutable $sentAt,
        public DateTimeImmutable $expiresAt,
        public ?string $invitedBy,
    ) {}
}
