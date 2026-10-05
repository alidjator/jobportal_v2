<?php

namespace Src\Company\Application\Invitations;

use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRole;

/** Who invites whom to which company. Exactly one of `invitedByUserId` (a company admin) and `invitedByAdminId` (a platform admin) is set. */
final readonly class IssueInvitationCommand
{
    public function __construct(
        public Company $company,
        public string $email,
        public CompanyRole $role,
        public ?int $invitedByUserId,
        public ?int $invitedByAdminId,
        public string $language,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
