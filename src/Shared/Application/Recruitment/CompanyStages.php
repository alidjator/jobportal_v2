<?php

namespace Src\Shared\Application\Recruitment;

/** Lets other contexts (Assessment) read the stages of a company without reading Recruitment's tables. Provided by Recruitment. */
interface CompanyStages
{
    /**
     * The stages of the company in pipeline order, keyed by id.
     *
     * @return array<int, CompanyStage>
     */
    public function ofCompany(int $companyId): array;
}
