<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\JobSeeker\Application\JakartaToday;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The candidate search of a company member. Nothing is logged here: a list shows no more than a headline; opening a profile is what the log records. */
final readonly class SearchCandidates
{
    public function __construct(private CandidateAccess $access, private CandidateReader $candidates, private CandidateListItems $items, private JakartaToday $today) {}

    /**
     * @return Page<CandidateListItem>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor, CandidateSearchQuery $query, PageRequest $request, string $language): Page
    {
        $this->access->viewer($actor);
        $page = $this->candidates->search($query, $request, $this->today->date());

        return new Page($this->items->of($page->items, $language), $page->total, $page->request);
    }
}
