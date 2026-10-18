<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Platform\Application\Scheduler\ScheduledTaskRun;
use Src\Platform\Application\Scheduler\ScheduledTaskRunRepository;

final readonly class DatabaseScheduledTaskRunRepository implements ScheduledTaskRunRepository
{
    private const string TABLE = 'platform_scheduled_task_runs';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function recordStarted(string $task, string $expression, DateTimeImmutable $at): void
    {
        $this->upsert($task, $expression, ['last_started_at' => $at->format(self::TIME_FORMAT)]);
    }

    public function recordFinished(string $task, string $expression, DateTimeImmutable $at): void
    {
        $this->upsert($task, $expression, ['last_finished_at' => $at->format(self::TIME_FORMAT), 'consecutive_failures' => 0]);
    }

    public function recordFailed(string $task, string $expression, DateTimeImmutable $at, string $failure): void
    {
        $this->upsert($task, $expression, ['last_failed_at' => $at->format(self::TIME_FORMAT), 'last_failure' => $failure], increment: 'consecutive_failures');
    }

    public function all(): array
    {
        return $this->connection->table(self::TABLE)->orderBy('task')->get()->map(fn (object $row): ScheduledTaskRun => new ScheduledTaskRun(
            (string) $row->task,
            (string) $row->expression,
            $this->time($row->last_started_at),
            $this->time($row->last_finished_at),
            $this->time($row->last_failed_at),
            $row->last_failure === null ? null : (string) $row->last_failure,
            (int) $row->consecutive_failures,
            $this->time($row->alerted_at),
        ))->all();
    }

    public function markAlerted(string $task, ?DateTimeImmutable $at): void
    {
        $this->connection->table(self::TABLE)->where('task', $task)->update(['alerted_at' => $at?->format(self::TIME_FORMAT)]);
    }

    public function forgetExcept(array $tasks): void
    {
        $this->connection->table(self::TABLE)->whereNotIn('task', $tasks)->delete();
    }

    /** @param  array<string, mixed>  $values */
    private function upsert(string $task, string $expression, array $values, ?string $increment = null): void
    {
        $this->connection->table(self::TABLE)->insertOrIgnore(['task' => $task, 'expression' => $expression, 'consecutive_failures' => 0]);

        $query = $this->connection->table(self::TABLE)->where('task', $task);

        if ($increment !== null) {
            $query->increment($increment, 1, [...$values, 'expression' => $expression]);

            return;
        }

        $query->update([...$values, 'expression' => $expression]);
    }

    private function time(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value);
    }
}
