<?php

namespace Src\Company\Application\Activity;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The activity log of the actor's company for its company admins: what its members did (sign-ins, security changes, downloads of applicant data, exports,
 * team and job changes, settings) and what the platform did to it, newest first, as far back as the audit log is kept. Each entry shows the IP address
 * and user agent of a member's action (so a company admin can spot a suspicious sign-in); a platform admin's address stays with the platform.
 */
final readonly class ListCompanyActivity
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyActivityReader $reader,
    ) {}

    /**
     * @return CursorPage<CompanyActivityRow>
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function execute(Actor $actor, CompanyActivityFilter $filter, CursorRequest $request): CursorPage
    {
        return $this->reader->read((int) $this->guard->company($actor)->id, $filter, $request);
    }
}
