<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobKitDefaults;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Clears the default kit of a job for an interview stage (nothing happens when it had none). */
final readonly class RemoveJobInterviewKit
{
    public function __construct(
        private RecruitmentAccess $access,
        private JobInterviewStages $stages,
        private JobKitDefaults $defaults,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the job or the stage is not one the member may use
     * @throws BusinessRuleViolation `stage-not-interview`
     */
    public function execute(Actor $actor, int $jobId, int $stageId): void
    {
        $member = $this->access->viewer($actor, CompanyPermission::JobsCreate);
        $this->access->job($member, $jobId);
        $this->stages->find($member, $stageId);

        $this->transactions->run(fn () => $this->defaults->remove($jobId, $stageId));
    }
}
