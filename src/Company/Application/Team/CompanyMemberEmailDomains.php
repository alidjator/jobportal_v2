<?php

namespace Src\Company\Application\Team;

use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Company\MemberEmailDomains;

final readonly class CompanyMemberEmailDomains implements MemberEmailDomains
{
    public function __construct(private CompanyMembershipResolver $memberships, private InvitationEmailDomains $domains) {}

    public function assertAllowed(int $userId, string $email): void
    {
        $membership = $this->memberships->forUser($userId);

        if ($membership !== null) {
            $this->domains->assertAllowed($membership->companyId, $email);
        }
    }
}
