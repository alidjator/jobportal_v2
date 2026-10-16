<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Recruitment\HiringManagerBoundary;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A member who may edit jobs sets the stage from which the hiring managers of a job see applications (null: the company's first interview stage). It works in
 * any status of the job. Applications that already qualify under the new boundary become visible at once; no application is ever hidden again.
 */
final readonly class SetHiringManagerBoundary
{
    public function __construct(
        private JobAccess $access,
        private JobRepository $jobs,
        private HiringManagerBoundary $boundary,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws BusinessRuleViolation `invalid-stage` with `errors` on `stage_id`
     */
    public function execute(Actor $actor, int $jobId, ?int $stageId): void
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsCreate);

        $this->transactions->run(function () use ($member, $jobId, $stageId): void {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);

            if ($stageId !== null && ! $this->boundary->isBoundaryStage($member->companyId, $stageId)) {
                throw new BusinessRuleViolation('The stage must be an active stage of the company.', 'invalid-stage', extensions: ['errors' => ['stage_id' => ['The stage must be an active stage of the company.']]]);
            }

            if ($job->hiringManagerFromStageId === $stageId) {
                return;
            }

            $this->jobs->save($job->withHiringManagerBoundary($stageId));
            $this->boundary->reevaluateJob($jobId);
        });
    }
}
