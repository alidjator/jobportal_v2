<?php

namespace Src\Company\Application\ImportSummary;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\ImportSummaryRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Closes the card of the import summary for the company; the full list stays readable until the summary is purged. */
final readonly class DismissCompanyImportSummary
{
    public function __construct(private CompanyAdminGuard $guard, private ImportSummaryRepository $summaries, private Clock $clock) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound `no-import-summary`
     */
    public function execute(Actor $actor): void
    {
        $company = $this->guard->company($actor);

        if (! $this->summaries->dismiss((int) $company->id, (int) $actor->id, $this->clock->now())) {
            throw new EntityNotFound('There is no import summary for this company.', 'no-import-summary');
        }
    }
}
