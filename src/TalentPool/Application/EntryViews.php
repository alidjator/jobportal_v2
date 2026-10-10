<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\TalentPool\Domain\Category;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\Entry;

/** Turns entries into what the company sees, looking up who can still be found and the category names once per page. */
final readonly class EntryViews
{
    public function __construct(private CandidateDirectory $candidates, private CategoryRepository $categories) {}

    /**
     * @param  Page<Entry>  $page
     * @return Page<EntryView>
     */
    public function of(int $companyId, Page $page): Page
    {
        return new Page($this->views($companyId, $page->items), $page->total, $page->request);
    }

    /**
     * @param  list<Entry>  $entries
     * @return list<EntryView>
     */
    public function views(int $companyId, array $entries): array
    {
        $userIds = array_values(array_unique(array_map(static fn (Entry $entry): int => $entry->candidateUserId, $entries)));
        $findable = array_flip($this->candidates->findableIds($userIds));
        $refs = $this->candidates->refs(array_values(array_filter($userIds, static fn (int $id): bool => isset($findable[$id]))));

        $names = [];

        foreach ($this->categories->ofCompany($companyId) as $category) {
            $names[(int) $category->id] = $category->name;
        }

        return array_map(static fn (Entry $entry): EntryView => new EntryView(
            (int) $entry->id,
            $entry->categoryId,
            $entry->categoryId === null ? null : ($names[$entry->categoryId] ?? null),
            isset($findable[$entry->candidateUserId]) ? ($refs[$entry->candidateUserId]->slug ?? null) : null,
            isset($findable[$entry->candidateUserId]) ? ($refs[$entry->candidateUserId]->name ?? null) : null,
            $entry->createdAt,
        ), $entries);
    }
}
