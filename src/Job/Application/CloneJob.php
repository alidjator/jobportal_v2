<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A member copies a job they may see into a new draft: every field, skill, tag and benefit, but no deadline, assigned only to the person cloning, and
 * without applications. Any status can be cloned, so an old job is easy to reopen as a fresh one.
 */
final readonly class CloneJob
{
    public function __construct(
        private JobAccess $access,
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return int the id of the new draft
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, int $jobId): int
    {
        $member = $this->access->member($actor);
        $source = $this->access->job($member, $jobId);

        return $this->transactions->run(function () use ($member, $source): int {
            $copy = $this->jobs->create(Job::draft($member->companyId, $source->slug, $source->content->with(['deadline' => null]), $member->userId));
            $this->assignments->add((int) $copy->id, $member->userId, null, $this->clock->now());

            return (int) $copy->id;
        });
    }
}
