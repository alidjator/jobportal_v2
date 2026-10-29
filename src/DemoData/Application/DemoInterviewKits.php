<?php

namespace Src\DemoData\Application;

use LogicException;
use Src\Company\Domain\MemberPreset;
use Src\Recruitment\Application\CreateInterviewKit;
use Src\Recruitment\Application\ListStages;
use Src\Recruitment\Application\SetJobInterviewKit;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;

/**
 * The interview kits of the verified company (`DemoCast::interviewKits()`): the recruiter adds them to the library and sets each job's default kit for the
 * company's default interview stage, so the interviews the run schedules afterwards copy their questions. It runs after the jobs and before the applications.
 */
final readonly class DemoInterviewKits
{
    public function __construct(
        private CreateInterviewKit $createKit,
        private SetJobInterviewKit $setJobKit,
        private ListStages $stages,
        private DemoActors $actors,
    ) {}

    /**
     * @param  array<string, array{company_id: int, admin_id: int, members: array<string, int>}>  $teams
     * @param  array<string, int>  $jobIds
     * @return int how many kits were made
     */
    public function build(array $teams, array $jobIds): int
    {
        $recruiter = $this->actors->member($teams[DemoCast::SINAR]['members'][MemberPreset::Recruiter->value]);
        $kitIds = [];

        foreach (DemoCast::interviewKits() as $key => $kit) {
            $kitIds[$key] = (int) $this->createKit->execute($recruiter, $kit['name'], $kit['questions'])->id;
        }

        $stageId = $this->interviewStageId($recruiter);

        foreach (DemoCast::jobKits() as $job => $kit) {
            $this->setJobKit->execute($recruiter, $jobIds[$job], $stageId, $kitIds[$kit]);
        }

        return count($kitIds);
    }

    private function interviewStageId(Actor $recruiter): int
    {
        foreach ($this->stages->execute($recruiter) as $stage) {
            if ($stage->isDefault && $stage->type === StageType::Interview) {
                return (int) $stage->id;
            }
        }

        throw new LogicException('The company has no default interview stage.');
    }
}
