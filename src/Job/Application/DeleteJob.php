<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member deletes a draft they may see, permanently. Any other job is closed instead, so the history of applications stays whole. */
final readonly class DeleteJob
{
    public function __construct(
        private JobAccess $access,
        private JobRepository $jobs,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-draft`
     */
    public function execute(Actor $actor, int $jobId): void
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsCreate);

        $this->transactions->run(function () use ($member, $jobId): void {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);

            if (! $job->canBeDeleted()) {
                throw new StateConflict('Only a draft can be deleted; close the job instead.', 'job-not-draft');
            }

            $this->jobs->delete($jobId);
        });
    }
}
