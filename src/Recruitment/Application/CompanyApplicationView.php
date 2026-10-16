<?php

namespace Src\Recruitment\Application;

/**
 * The detail of an application for the company's team, with the names the history needs: the company's stage names by stage id (a deleted stage is missing)
 * and the names of the team members that moved it, by account id.
 */
final readonly class CompanyApplicationView
{
    /**
     * @param  array<int, string>  $stageNames
     * @param  array<int, string>  $actorNames
     * @param  list<ApplicationInterviewResult>  $interviews  newest first, with the results the viewer may see
     * @param  list<OtherApplicationView>  $otherApplications  the person's other applications to this company that the viewer may see, newest first
     */
    public function __construct(
        public RecruiterApplicationDetailData $data,
        public ?string $jobTitle,
        public string $stageName,
        public array $stageNames,
        public array $actorNames,
        public ApplicationRatingView $rating,
        public array $interviews = [],
        public int $daysInStage = 0,
        public bool $stale = false,
        public array $otherApplications = [],
        public int $othersHidden = 0,
    ) {}
}
