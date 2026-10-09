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
    public function __construct(private CandidateAccess $access, private CandidateReader $candidates, private CandidateLabels $labels, private JakartaToday $today) {}

    /**
     * @return Page<CandidateListItem>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor, CandidateSearchQuery $query, PageRequest $request, string $language): Page
    {
        $this->access->viewer($actor);
        $page = $this->candidates->search($query, $request, $this->today->date());
        $rows = $page->items;

        $provinces = $this->labels->provinces(array_map(static fn (CandidateRow $row): ?int => $row->provinceId, $rows));
        $cities = $this->labels->cities(array_map(static fn (CandidateRow $row): ?int => $row->cityId, $rows));
        $levels = $this->labels->names('experience-levels', array_map(static fn (CandidateRow $row): ?int => $row->experienceLevelId, $rows), $language);
        $educations = $this->labels->names('education-levels', array_map(static fn (CandidateRow $row): ?int => $row->highestEducationLevelId, $rows), $language);

        return new Page(array_map(static fn (CandidateRow $row): CandidateListItem => new CandidateListItem(
            $row->slug,
            $row->name,
            $row->avatarPath !== null,
            $row->headline,
            CandidateLabels::named($provinces, $row->provinceId),
            CandidateLabels::named($cities, $row->cityId),
            CandidateLabels::named($levels, $row->experienceLevelId),
            $row->availability?->value,
            CandidateLabels::named($educations, $row->highestEducationLevelId),
            $row->updatedAt,
        ), $rows), $page->total, $page->request);
    }
}
