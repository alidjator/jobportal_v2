<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts ask about a company, without reading Company's tables. Provided by Company. */
interface CompanyDirectory
{
    public function exists(int $companyId): bool;

    /** True when the company exists and is not suspended. */
    public function isActive(int $companyId): bool;

    /**
     * Name, slug and status of these companies, keyed by company id; an id without a company is missing.
     *
     * @param  list<int>  $companyIds
     * @return array<int, CompanySummary>
     */
    public function summaries(array $companyIds): array;

    /**
     * The ids of the companies whose name contains the text, whatever the letter case (`%`, `_` and `\` count as themselves), at most `$limit`.
     *
     * @return list<int>
     */
    public function idsByName(string $text, int $limit): array;
}
