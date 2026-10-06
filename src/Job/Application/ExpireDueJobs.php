<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/**
 * Sets live jobs whose deadline day is over (before today in Jakarta time) to `expired`, in batches. Each job is handled in its own transaction with its row
 * locked and checked again, so a job that was closed or changed meanwhile is left alone, and running it twice changes nothing more.
 */
final readonly class ExpireDueJobs
{
    private const int BATCH_SIZE = 200;

    public function __construct(
        private JobRepository $jobs,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return int how many jobs expired */
    public function execute(): int
    {
        $now = $this->clock->now();
        $today = JakartaDate::today($now);
        $expired = 0;

        do {
            $ids = $this->jobs->idsDueForExpiry($today, self::BATCH_SIZE);
            $changedInBatch = 0;

            foreach ($ids as $id) {
                $event = $this->transactions->run(function () use ($id, $today, $now): ?JobStatusChanged {
                    $this->jobs->lock($id);
                    $job = $this->jobs->find($id);

                    if ($job === null || $job->status !== JobStatus::Active || $job->content->deadline === null || $job->content->deadline >= $today) {
                        return null;
                    }

                    $this->jobs->save($job->expire());

                    return new JobStatusChanged($id, $job->companyId, JobStatusChanged::EXPIRED, JobStatus::Active->value, JobStatus::Expired->value, null, null, null, $now);
                });

                if ($event !== null) {
                    $this->events->dispatch([$event]);
                    $changedInBatch++;
                }
            }

            $expired += $changedInBatch;
        } while (count($ids) === self::BATCH_SIZE && $changedInBatch > 0);

        return $expired;
    }
}
