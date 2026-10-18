<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\TalentPool\Domain\EntryRepository;

/**
 * The candidates in the member's company's talent pool with their summaries, the most recently saved first. A company keeps at most 1,000 entries, so a search or an
 * ordering by activity loads the summaries of all of them and filters, sorts and pages in memory; the plain list pages first and looks up only the page.
 */
final readonly class ListEntries
{
    public function __construct(private TalentPoolAccess $access, private EntryRepository $entries, private EntryViews $views) {}

    /**
     * @return Page<EntryView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function execute(Actor $actor, EntryFilter $filter, PageRequest $request, string $language): Page
    {
        $member = $this->access->member($actor);

        if ($filter->pagesFirst()) {
            $page = $this->entries->pageOfCompany($member->companyId, $filter->categoryId, $filter->source, $request);

            return new Page($this->views->views($actor, $member, $page->items, $language), $page->total, $request);
        }

        $views = $this->views->views($actor, $member, $this->entries->allOfCompany($member->companyId, $filter->categoryId, $filter->source), $language);

        $query = $filter->query;

        if ($query !== null) {
            $views = array_values(array_filter($views, fn (EntryView $view): bool => $this->matches($view, $query)));
        }

        if ($filter->sort === EntrySort::LastActivity) {
            usort($views, static fn (EntryView $a, EntryView $b): int => [$b->lastActivity(), $b->id] <=> [$a->lastActivity(), $a->id]);
        }

        return new Page(array_slice($views, $request->offset(), $request->perPage), count($views), $request);
    }

    /** Case-insensitive "contains" over what the company sees of an available entry; an unavailable entry shows nothing to match. */
    private function matches(EntryView $view, string $query): bool
    {
        if (! $view->isAvailable()) {
            return false;
        }

        foreach ([$view->candidateName, $view->headline, $view->latestPosition['title'] ?? null, $view->latestPosition['employer'] ?? null, ...$view->skills] as $text) {
            if ($text !== null && mb_stripos($text, $query) !== false) {
                return true;
            }
        }

        return false;
    }
}
