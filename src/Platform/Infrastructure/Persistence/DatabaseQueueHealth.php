<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Platform\Application\Queue\FailedJobs;
use Src\Platform\Application\Queue\QueueHealth;
use Src\Platform\Application\Queue\QueueStatus;

final readonly class DatabaseQueueHealth implements QueueHealth
{
    /** How many of the newest failed jobs are read for their class and error; the payloads can be large. */
    private const int SAMPLE = 200;

    private const int FAILURE_LENGTH = 300;

    public function __construct(private ConnectionInterface $connection) {}

    public function queues(DateTimeImmutable $now): array
    {
        $statuses = [];

        foreach ($this->connection->table('jobs')->whereNull('reserved_at')->groupBy('queue')
            ->selectRaw('queue, COUNT(*) as waiting, MIN(CASE WHEN available_at <= ? THEN available_at END) as oldest', [$now->getTimestamp()])
            ->get() as $row) {
            $statuses[(string) $row->queue] = [(int) $row->waiting, $row->oldest === null ? null : max(0, $now->getTimestamp() - (int) $row->oldest), 0];
        }

        foreach ($this->connection->table('failed_jobs')
            ->where('failed_at', '>=', $now->modify('-24 hours')->format('Y-m-d H:i:s'))
            ->groupBy('queue')
            ->selectRaw('queue, COUNT(*) as total')
            ->get() as $row) {
            $statuses[(string) $row->queue] ??= [0, null, 0];
            $statuses[(string) $row->queue][2] = (int) $row->total;
        }

        ksort($statuses);

        return array_map(
            fn (array $values, string $queue): QueueStatus => new QueueStatus($queue, ...$values),
            $statuses,
            array_keys($statuses),
        );
    }

    public function failedQueues(): array
    {
        return $this->connection->table('failed_jobs')->distinct()->orderBy('queue')->pluck('queue')->map(fn (mixed $queue): string => (string) $queue)->all();
    }

    public function failedSince(string $queue, int $afterId): ?FailedJobs
    {
        $count = $this->connection->table('failed_jobs')->where('queue', $queue)->where('id', '>', $afterId)->count();

        if ($count === 0) {
            return null;
        }

        $rows = $this->connection->table('failed_jobs')->where('queue', $queue)->where('id', '>', $afterId)->orderByDesc('id')->limit(self::SAMPLE)->get(['id', 'payload', 'exception']);
        $classes = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            $class = is_array($payload) && is_string($payload['displayName'] ?? null) ? $payload['displayName'] : 'unknown';
            $classes[$class] = ($classes[$class] ?? 0) + 1;
        }

        arsort($classes);

        $newest = $rows->first();
        $line = trim(explode("\n", (string) $newest->exception, 2)[0]);

        return new FailedJobs($queue, $count, (int) $newest->id, $classes, mb_substr($line, 0, self::FAILURE_LENGTH));
    }
}
