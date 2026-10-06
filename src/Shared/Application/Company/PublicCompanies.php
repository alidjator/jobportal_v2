<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts show a company to the public without reading Company's tables. Provided by Company. */
interface PublicCompanies
{
    /** The company's public card, or null when it does not exist, is suspended or is hidden by its own admin (all three look the same to the public). */
    public function card(int $companyId): ?PublicCompanyCard;
}
