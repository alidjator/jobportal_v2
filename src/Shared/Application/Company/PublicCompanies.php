<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts show a company to the public without reading Company's tables. Provided by Company. */
interface PublicCompanies
{
    /** The company's public card, or null when it does not exist, is suspended or is hidden by its own admin (all three look the same to the public). */
    public function card(int $companyId): ?PublicCompanyCard;

    /** Like `card`, found by the company's slug. */
    public function cardBySlug(string $slug): ?PublicCompanyCard;

    /**
     * The public cards of these companies in one go, keyed by company id; a company that is missing, suspended or hidden is left out.
     *
     * @param  list<int>  $companyIds
     * @return array<int, PublicCompanyCard>
     */
    public function cards(array $companyIds): array;

    /**
     * The ids of the companies the public must not see: suspended ones and the ones hidden by their own admin. Meant for excluding their jobs from a list;
     * these companies are few, so the list is short.
     *
     * @return list<int>
     */
    public function hiddenIds(): array;
}
