<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Recruitment\JobRoster;
use Src\Shared\Application\Recruitment\RosterEntry;

/** The applications of one job for the members who may see them, for the other contexts (`JobRoster`): the same scope as the comparison of interview results. */
final readonly class RecruiterJobRoster implements JobRoster
{
    public function __construct(
        private RecruitmentAccess $access,
        private RecruiterApplicationReader $reader,
        private InterviewResults $results,
        private HiringManagerScope $scope,
    ) {}

    public function applications(Actor $actor, int $jobId, ?string $stageType = null): array
    {
        [, $query] = $this->scoped($actor, $jobId, $stageType);

        return $this->reader->matchScores($query);
    }

    public function entries(Actor $actor, int $jobId, array $applicationIds, ?string $stageType = null): array
    {
        [$member, $query] = $this->scoped($actor, $jobId, $stageType);

        $rows = $this->reader->rows($applicationIds, $query);
        $interviews = $this->results->latestInterviews(array_keys($rows), $member->userId);
        $entries = [];

        foreach ($rows as $id => $row) {
            $entries[$id] = new RosterEntry(
                $id,
                $row->isDeleted,
                $row->isDeleted ? null : $row->name,
                $row->stageId,
                $row->stageType->value,
                $row->stageName,
                $row->matchScore,
                $row->ratingAverage,
                $interviews[$id]->result?->score ?? null,
            );
        }

        return $entries;
    }

    /** @return array{0: RecruitmentMember, 1: RecruiterApplicationQuery} */
    private function scoped(Actor $actor, int $jobId, ?string $stageType): array
    {
        $member = $this->access->anyOf($actor, CompanyPermission::ApplicationsView, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $jobId);

        $query = (new RecruiterApplicationQuery($jobId, null, $stageType === null ? null : StageType::from($stageType), activeOnly: true))
            ->withHiringManagerScope($this->scope->isBound($member, $jobId));

        return [$member, $query];
    }
}
