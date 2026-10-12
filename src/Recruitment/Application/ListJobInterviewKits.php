<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Recruitment\Domain\JobKitDefaults;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The default kits of a job, one per interview stage that has one, in the order of the stages. */
final readonly class ListJobInterviewKits
{
    public function __construct(
        private RecruitmentAccess $access,
        private JobKitDefaults $defaults,
        private InterviewKitRepository $kits,
        private StageRepository $stages,
    ) {}

    /**
     * @return list<JobKitDefault>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the job is not one the member may see
     */
    public function execute(Actor $actor, int $jobId): array
    {
        $member = $this->access->member($actor);
        $this->access->job($member, $jobId);

        $kitIds = $this->defaults->forJob($jobId);
        $names = [];

        foreach ($this->kits->forCompany($member->companyId) as $kit) {
            $names[(int) $kit->id] = $kit->name;
        }

        $rows = [];

        foreach ($this->stages->forCompany($member->companyId) as $stage) {
            $kitId = $kitIds[(int) $stage->id] ?? null;

            if ($kitId !== null && isset($names[$kitId])) {
                $rows[] = new JobKitDefault((int) $stage->id, $stage->name, $kitId, $names[$kitId]);
            }
        }

        return $rows;
    }
}
