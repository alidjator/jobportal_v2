<?php

namespace Src\Platform\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/**
 * The queue as the database sees it: queue jobs that failed in the last 24 hours and jobs waiting to run, per queue. The outcome of a mail or a notification is not
 * recorded, so a mail that failed shows up here as a failed job.
 */
final readonly class OperationsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

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

        return [
            'failed_last_24_hours' => array_sum($failed),
            'failed_by_queue' => (object) $failed,
            'waiting' => array_sum($waiting),
            'waiting_by_queue' => (object) $waiting,
        ];
    }
}
