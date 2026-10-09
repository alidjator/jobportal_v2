<?php

namespace Src\JobSeeker\Application\Candidates;

/** Turns candidate rows into the list items of the search and of the recommendations, with the names in the language of the request. */
final readonly class CandidateListItems
{
    public function __construct(private CandidateLabels $labels) {}

    /**
     * @param  list<CandidateRow>  $rows
     * @return list<CandidateListItem> in the order of the rows
     */
    public function of(array $rows, string $language): array
    {
        $provinces = $this->labels->provinces(array_map(static fn (CandidateRow $row): ?int => $row->provinceId, $rows));
        $cities = $this->labels->cities(array_map(static fn (CandidateRow $row): ?int => $row->cityId, $rows));
        $levels = $this->labels->names('experience-levels', array_map(static fn (CandidateRow $row): ?int => $row->experienceLevelId, $rows), $language);
        $educations = $this->labels->names('education-levels', array_map(static fn (CandidateRow $row): ?int => $row->highestEducationLevelId, $rows), $language);

        return array_map(static fn (CandidateRow $row): CandidateListItem => new CandidateListItem(
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
        ), $rows);
    }
}
