<?php

namespace Src\Company\Application\NameChange;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\NameChangeRequest;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A member who may change the company profile reads the name change requests of their company, the newest 50 first. */
final readonly class ListMyNameChangeRequests
{
    private const int LIMIT = 50;

    public function __construct(private CompanyAdminGuard $guard, private NameChangeRequestRepository $requests) {}

    /**
     * @return list<NameChangeRequest>
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        $company = $this->guard->companyWith($actor, CompanyPermission::CompanyProfile);

        return $this->requests->ofCompany((int) $company->id, self::LIMIT);
    }
}
