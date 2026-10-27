<?php

namespace Src\Assessment\Application\Register;

use Src\Assessment\Domain\RegisterEntry;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\Page;

/** Adds the company names to a page of register lines, with one read for the whole page. */
final readonly class RegisterItems
{
    public function __construct(private CompanyDirectory $companies) {}

    /**
     * @param  Page<RegisterEntry>  $page
     * @return Page<RegisterItem>
     */
    public function of(Page $page): Page
    {
        $names = $this->companyNames($page->items);

        return new Page(
            array_map(static fn (RegisterEntry $entry): RegisterItem => new RegisterItem($entry, $entry->companyId === null ? null : ($names[$entry->companyId] ?? null)), $page->items),
            $page->total,
            $page->request,
        );
    }

    /**
     * @param  list<RegisterEntry>  $entries
     * @return array<int, string>
     */
    public function companyNames(array $entries): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (RegisterEntry $entry): ?int => $entry->companyId, $entries))));
        $names = [];

        foreach ($this->companies->summaries($ids) as $id => $summary) {
            $names[$id] = $summary->name;
        }

        return $names;
    }
}
