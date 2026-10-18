<?php

namespace Src\TalentPool\Application;

use Src\TalentPool\Domain\EntrySource;

/** What narrows the talent pool list: a category, a source and a search text over the name, headline, skills and latest position of the available entries. */
final readonly class EntryFilter
{
    public function __construct(
        public ?int $categoryId = null,
        public ?EntrySource $source = null,
        public ?string $query = null,
        public EntrySort $sort = EntrySort::SavedAt,
    ) {}

    /** Whether the list can be cut into pages before the entries are looked up: nothing depends on the person's data. */
    public function pagesFirst(): bool
    {
        return $this->query === null && $this->sort === EntrySort::SavedAt;
    }
}
