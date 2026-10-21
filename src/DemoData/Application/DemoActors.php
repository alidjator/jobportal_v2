<?php

namespace Src\DemoData\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyMembershipResolver;

/** The actors of the demo run, built as the HTTP layer builds them: a company member carries their membership. */
final readonly class DemoActors
{
    public function __construct(private CompanyMembershipResolver $memberships) {}

    public function member(int $userId): Actor
    {
        return Actor::companyMember($userId)->withMembership($this->memberships->forUser($userId));
    }
}
