<?php

namespace Src\Job\Application\Admin;

use DateTimeImmutable;
use Src\Job\Application\JakartaDate;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Job;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * What every admin decision about a job has in common: only an admin may decide, the job row is locked, the decision is applied (a wrong status is a 409
 * before anything else), stored, and announced after the transaction committed. A subclass says which decision it is and how the job changes; it also
 * receives today's date in Jakarta time, for the decisions that depend on the deadline.
 */
abstract class AdminJobDecision
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly TransactionManager $transactions,
        private readonly EventDispatcher $events,
        private readonly Clock $clock,
    ) {}

    /** One of the `JobDecided` decision names. */
    abstract protected function decision(): string;

    /**
     * The job after the decision; throws a `StateConflict` when its status (or, for an approval, its deadline) does not allow it.
     *
     * @param  DateTimeImmutable  $today  the date in Jakarta time, as midnight UTC, comparable with a deadline
     */
    abstract protected function apply(Job $job, int $adminId, ?string $reason, DateTimeImmutable $at, DateTimeImmutable $today): Job;

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the job does not exist
     * @throws StateConflict `job-not-pending-review`, `job-not-active`, `job-not-taken-down` or `job-deadline-passed`
     */
    protected function run(Actor $actor, int $jobId, ?string $reason, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can decide about jobs.');
        }

        $now = $this->clock->now();
        $today = JakartaDate::today($now);
        $adminId = (int) $actor->id;

        $event = $this->transactions->run(function () use ($jobId, $adminId, $reason, $ipAddress, $userAgent, $now, $today): JobDecided {
            $this->jobs->lock($jobId);
            $job = $this->jobs->find($jobId) ?? throw EntityNotFound::of('Job', $jobId);

            $decided = $this->apply($job, $adminId, $reason, $now, $today);
            $this->jobs->save($decided);

            return new JobDecided(
                $jobId, $job->companyId, $this->decision(), $job->status->value, $decided->status->value, $reason, $adminId, $ipAddress, $userAgent, $now,
            );
        });

        $this->events->dispatch([$event]);
    }
}
