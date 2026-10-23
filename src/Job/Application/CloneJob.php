<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobCloned;
use Src\Job\Domain\Job;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Application\Workforce\RequisitionGate;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A member copies a job they may see into a new draft: every field, skill, tag, benefit and work location (the default tests of Assessment follow `JobCloned`), but no deadline, assigned only to the person cloning, and
 * without applications. Any status can be cloned, so an old job is easy to reopen as a fresh one.
 */
final readonly class CloneJob
{
    public function __construct(
        private JobAccess $access,
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private RequisitionGate $requisitions,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return int the id of the new draft
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     */
    public function execute(Actor $actor, int $jobId): int
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsCreate);
        $source = $this->access->job($member, $jobId);

        // A copy has no requisition of its own, so a tenant that opens jobs only for requisitions cannot clone.
        if ($this->requisitions->isRequired($member->companyId)) {
            throw new BusinessRuleViolation('This tenant opens its jobs from an approved requisition, so a job cannot be cloned.', 'requisition-required');
        }

        $copyId = $this->transactions->run(function () use ($member, $source): int {
            $copy = $this->jobs->create(Job::draft($member->companyId, $source->slug, $source->content->with(['deadline' => null]), $member->userId));
            $this->assignments->add((int) $copy->id, $member->userId, null, $this->clock->now());

            return (int) $copy->id;
        });

        $this->events->dispatch([new JobCloned((int) $source->id, $copyId, $member->companyId, $member->userId, $this->clock->now())]);

        return $copyId;
    }
}
