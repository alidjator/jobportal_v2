<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Platform\Application\Queue\QueueAlert;
use Src\Platform\Application\Queue\QueueAlertKind;
use Src\Platform\Application\Queue\QueueAlertRepository;

final readonly class DatabaseQueueAlertRepository implements QueueAlertRepository
{
    private const string TABLE = 'platform_queue_alerts';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function all(): array
    {
        return $this->connection->table(self::TABLE)->orderBy('kind')->orderBy('queue')->get()->map(fn (object $row): QueueAlert => new QueueAlert(
            QueueAlertKind::from((string) $row->kind),
            (string) $row->queue,
            $row->alerted_at === null ? null : new DateTimeImmutable((string) $row->alerted_at),
            $row->recovered_at === null ? null : new DateTimeImmutable((string) $row->recovered_at),
            (int) $row->last_failed_job_id,
        ))->all();
    }

    public function markAlerted(QueueAlertKind $kind, string $queue, DateTimeImmutable $at, ?int $lastFailedJobId = null): void
    {
        $this->ensure($kind, $queue)->update([
            'alerted_at' => $at->format(self::TIME_FORMAT),
            'recovered_at' => null,
            ...($lastFailedJobId === null ? [] : ['last_failed_job_id' => $lastFailedJobId]),
        ]);
    }

    public function markRecovered(QueueAlertKind $kind, string $queue, DateTimeImmutable $at): void
    {
        $this->ensure($kind, $queue)->update(['recovered_at' => $at->format(self::TIME_FORMAT)]);
    }

    private function ensure(QueueAlertKind $kind, string $queue): Builder
    {
        $this->connection->table(self::TABLE)->insertOrIgnore(['kind' => $kind->value, 'queue' => $queue, 'last_failed_job_id' => 0]);

        return $this->connection->table(self::TABLE)->where('kind', $kind->value)->where('queue', $queue);
    }
}
