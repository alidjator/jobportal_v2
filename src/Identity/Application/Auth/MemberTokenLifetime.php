<?php

namespace Src\Identity\Application\Auth;

use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Organization\GroupDirectory;

/**
 * How long a token of a public account lives (`docs/design/erp-sso.md` section 4): 12 hours for a member of an internal tenant of an ERP group, 30 days for everyone
 * else (job seekers, members of external tenants). Chosen when the token is created, as an interval for `DateTimeImmutable::modify`.
 */
final readonly class MemberTokenLifetime
{
    public const string INTERNAL = '+12 hours';

    public function __construct(private CompanyMembershipResolver $memberships, private GroupDirectory $groups) {}

    public function forUser(int $userId): string
    {
        $membership = $this->memberships->forUser($userId);

        return $membership !== null && $this->groups->groupOfCompany($membership->companyId) !== null ? self::INTERNAL : SignInUser::TOKEN_LIFETIME;
    }
}
