<?php

namespace Src\Organization\Application\Reading;

use Src\Shared\Application\Company\CompanyMembership;

/** What a group member may read of one tenant: the group, the tenant, and the read-only membership the recruitment use cases then see. */
final readonly class TenantRead
{
    public function __construct(public int $groupId, public int $companyId, public CompanyMembership $membership) {}
}
