<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member closes a live job they may see before its deadline. A closed job can be reopened with a new deadline. */
final readonly class CloseJob
{
    public function __construct(
        private JobAccess $access,
        private JobRepository $jobs,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the member cannot see the job
     * @throws StateConflict `job-not-active`
     */
    public function execute(Actor $actor, int $jobId, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->member($actor);
        $now = $this->clock->now();

        $event = $this->transactions->run(function () use ($member, $jobId, $now, $ipAddress, $userAgent): JobStatusChanged {
            $this->jobs->lock($jobId);
            $job = $this->access->job($member, $jobId);

            $closed = $job->close();
            $this->jobs->save($closed);

            return new JobStatusChanged($jobId, $member->companyId, JobStatusChanged::CLOSED, $job->status->value, $closed->status->value, $member->userId, $ipAddress, $userAgent, $now);
        });

        $this->events->dispatch([$event]);
    }
}
