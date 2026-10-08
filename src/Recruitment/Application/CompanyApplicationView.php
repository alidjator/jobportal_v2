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
     */
    public function __construct(
        public RecruiterApplicationDetailData $data,
        public ?string $jobTitle,
        public string $stageName,
        public array $stageNames,
        public array $actorNames,
        public ApplicationRatingView $rating,
    ) {}
}
