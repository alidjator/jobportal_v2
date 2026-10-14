<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobKitDefaults;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Sets the kit a job preselects when an interview is scheduled in one of the company's interview stages. */
final readonly class SetJobInterviewKit
{
    public function __construct(
        private RecruitmentAccess $access,
        private JobInterviewStages $stages,
        private InterviewKitLookup $kits,
        private JobKitDefaults $defaults,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the job, the stage or the kit is not one the member may use
     * @throws BusinessRuleViolation `stage-not-interview`
     */
    public function execute(Actor $actor, int $jobId, int $stageId, int $kitId): JobKitDefault
    {
        $member = $this->access->viewer($actor, CompanyPermission::JobsCreate);
        $this->access->job($member, $jobId);
        $stage = $this->stages->find($member, $stageId);
        $kit = $this->kits->find($member, $kitId);

        $this->transactions->run(fn () => $this->defaults->set($jobId, $stageId, $kitId, $this->clock->now()));

        return new JobKitDefault((int) $stage->id, $stage->name, (int) $kit->id, $kit->name);
    }
}
