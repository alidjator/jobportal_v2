<?php

namespace Src\Shared\Application\Company;

/** Lets other contexts ask about a company, without reading Company's tables. Provided by Company. */
interface CompanyDirectory
{
    public function exists(int $companyId): bool;

    /** True when the company exists and is not suspended. */
    public function isActive(int $companyId): bool;

    /** Whether the company holds the verification badge (the same fact as `PublicCompanyCard::verified`, read also while the company is suspended or hidden). */
    public function isVerified(int $companyId): bool;

    /**
     * Locks the company's row until the surrounding transaction ends, so requests of one company that count and then insert (a daily quota) run one after the
     * other. Call it inside a transaction; outside one the lock is released at once.
     */
    public function lock(int $companyId): void;

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

    /**
     * The ids of up to `$limit` companies whose id is greater than `$afterId`, in id order (for walking every company in batches).
     *
     * @return list<int>
     */
    public function idsAfter(int $afterId, int $limit): array;
}
