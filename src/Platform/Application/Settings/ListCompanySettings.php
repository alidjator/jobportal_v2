<?php

namespace Src\Platform\Application\Settings;

use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The settings a company may override, with the global value, the company's own value and the one that applies to it. */
final readonly class ListCompanySettings
{
    public function __construct(
        private CompanyDirectory $companies,
        private CompanySettingViews $views,
    ) {}

    /**
     * @return list<CompanySettingView>
     *
     * @throws EntityNotFound when the company does not exist
     */
    public function execute(int $companyId): array
    {
        if (! $this->companies->exists($companyId)) {
            throw EntityNotFound::of('Company', $companyId);
        }

        return $this->views->all($companyId);
    }
}
