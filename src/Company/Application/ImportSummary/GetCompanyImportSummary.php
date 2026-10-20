<?php

namespace Src\Company\Application\ImportSummary;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\ImportSummary;
use Src\Company\Domain\ImportSummaryRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The import summary of the caller's company. */
final readonly class GetCompanyImportSummary
{
    public function __construct(private CompanyAdminGuard $guard, private ImportSummaryRepository $summaries) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound `no-import-summary` when the company has none, or it was purged
     */
    public function execute(Actor $actor): ImportSummary
    {
        return $this->summaries->find((int) $this->guard->company($actor)->id) ?? throw new EntityNotFound('There is no import summary for this company.', 'no-import-summary');
    }
}
