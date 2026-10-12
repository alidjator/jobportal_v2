<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\ScorecardRepository;

/** The interviews of an application with their results for one member of the team, newest first. */
final readonly class ApplicationInterviewResults
{
    public function __construct(
        private InterviewRepository $interviews,
        private InterviewResults $results,
        private InterviewPanel $panel,
        private ScorecardRepository $scorecards,
    ) {}

    /** @return list<ApplicationInterviewResult> */
    public function forApplication(int $applicationId, int $viewerId): array
    {
        $interviews = $this->interviews->forApplication($applicationId);
        $ids = array_values(array_filter(array_map(static fn (Interview $interview): ?int => $interview->id, $interviews)));
        $results = $this->results->forInterviews($ids);
        $panels = $this->panel->forInterviews($ids);

        return array_map(function (Interview $interview) use ($results, $panels, $viewerId): ApplicationInterviewResult {
            $id = (int) $interview->id;
            $members = [$interview->interviewerId, ...($panels[$id] ?? [])];
            $locked = in_array($viewerId, $members, true) && ! in_array($viewerId, $this->scorecards->sentBy($id), true);

            return new ApplicationInterviewResult($interview, count($members), $locked ? null : ($results[$id] ?? InterviewResult::none()));
        }, $interviews);
    }
}
