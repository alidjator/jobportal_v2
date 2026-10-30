<?php

namespace Src\Platform\Infrastructure\Dashboard;

use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Src\Platform\Application\Queue\QueueHealth;
use Src\Platform\Application\Queue\QueueStatus;
use Src\Platform\Application\Scheduler\ReadScheduler;
use Src\Platform\Application\Scheduler\ScheduledTaskStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/**
 * The queue as the database sees it: queue jobs that failed in the last 24 hours and jobs waiting to run, per queue. The outcome of a mail or a notification is not
 * recorded, so a mail that failed shows up here as a failed job. `queues` lists every queue with waiting jobs or recent failures, with the wait of its oldest due job and whether it stalled. The scheduler part lists the scheduled tasks, late and failing ones first, with the time of the last heartbeat.
 */
final readonly class OperationsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection, private Clock $clock, private ReadScheduler $scheduler, private QueueHealth $queues) {}

    public function key(): string
    {
        return 'operations';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        $failed = [];

        foreach ($this->connection->table('failed_jobs')
            ->where('failed_at', '>=', $this->clock->now()->modify('-24 hours')->format('Y-m-d H:i:s'))
            ->groupBy('queue')
            ->get(['queue', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $failed[(string) $row->queue] = (int) $row->total;
        }

        $waiting = [];

        foreach ($this->connection->table('jobs')->whereNull('reserved_at')->groupBy('queue')->get(['queue', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $waiting[(string) $row->queue] = (int) $row->total;
        }

        ksort($failed);
        ksort($waiting);

        $scheduler = $this->scheduler->execute();

        return [
            'failed_last_24_hours' => array_sum($failed),
            'failed_by_queue' => (object) $failed,
            'waiting' => array_sum($waiting),
            'waiting_by_queue' => (object) $waiting,
            'queues' => array_map(fn (QueueStatus $queue): array => [
                'queue' => $queue->queue,
                'waiting' => $queue->waiting,
                'oldest_wait_seconds' => $queue->oldestWaitSeconds,
                'failed_last_24h' => $queue->failedLast24Hours,
                'stalled' => $queue->stalled(),
            ], $this->queues->queues($this->clock->now())),
            'scheduler' => [
                'last_tick_at' => $scheduler->lastTickAt?->format(DateTimeInterface::ATOM),
                'tasks' => array_map(fn (ScheduledTaskStatus $task): array => [
                    'task' => $task->task,
                    'last_finished_at' => $task->lastFinishedAt?->format(DateTimeInterface::ATOM),
                    'late' => $task->late,
                    'failing' => $task->failing,
                ], $scheduler->tasks),
            ],
        ];
    }
}
