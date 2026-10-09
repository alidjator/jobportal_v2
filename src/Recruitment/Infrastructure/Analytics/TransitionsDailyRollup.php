<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Analytics\AnalyticsRollup;

/** Stage moves per day, job, recruiter, stage pair, rejection reason, source and match band, with the seconds spent. */
final readonly class TransitionsDailyRollup implements AnalyticsRollup
{
    public function __construct(private ConnectionInterface $connection, private TransitionRows $transitions) {}

    public function name(): string
    {
        return 'recruitment_transitions_daily';
    }

    public function earliestDay(): ?string
    {
        $first = $this->connection->table('recruitment_application_stage_history')->whereNotNull('from_type')->min('created_at');

        return $first === null ? null : AnalyticsDays::dayOf((string) $first);
    }

    public function rebuild(string $day): void
    {
        $sums = [];

        foreach ($this->transitions->between(AnalyticsDays::startUtc($day), AnalyticsDays::endUtc($day), null) as $row) {
            $key = implode('|', [$row['job_id'], $row['actor_id'], $row['from_type'], $row['to_type'], $row['reason'], $row['source'], $row['band']]);

            $sums[$key] ??= [
                'day' => $day,
                'company_id' => $row['company_id'],
                'job_id' => $row['job_id'],
                'actor_id' => $row['actor_id'],
                'from_type' => $row['from_type'],
                'to_type' => $row['to_type'],
                'rejection_reason' => $row['reason'],
                'source' => $row['source'],
                'match_band' => $row['band'],
                'transitions' => 0,
                'seconds_in_stage' => 0,
                'seconds_since_applied' => 0,
            ];

            $sums[$key]['transitions']++;
            $sums[$key]['seconds_in_stage'] += $row['in_stage'];
            $sums[$key]['seconds_since_applied'] += $row['since_applied'];
        }

        $this->connection->transaction(function () use ($day, $sums): void {
            $this->connection->table('recruitment_transitions_daily')->where('day', $day)->delete();

            foreach (array_chunk(array_values($sums), 500) as $chunk) {
                $this->connection->table('recruitment_transitions_daily')->insert($chunk);
            }
        });
    }
}
