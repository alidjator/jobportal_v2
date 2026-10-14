<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The email domains the company's invitations are limited to, or an empty list when there is no limit. */
final readonly class GetEmailDomains
{
    public function __construct(private CompanyAdminGuard $guard, private CompanyRepository $companies) {}

    /**
     * @return list<string>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        $company = $this->guard->company($actor);

        return $this->companies->invitationEmailDomains((int) $company->id) ?? [];
    }
}
