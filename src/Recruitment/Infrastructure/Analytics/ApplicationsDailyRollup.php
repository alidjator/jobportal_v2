<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\MatchBand;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Analytics\AnalyticsRollup;

/** Applications submitted per day, job, source and match band. */
final readonly class ApplicationsDailyRollup implements AnalyticsRollup
{
    public function __construct(private ConnectionInterface $connection) {}

    public function name(): string
    {
        return 'recruitment_applications_daily';
    }

    public function earliestDay(): ?string
    {
        $first = $this->connection->table('recruitment_applications')->min('applied_at');

        return $first === null ? null : AnalyticsDays::dayOf((string) $first);
    }

    public function rebuild(string $day): void
    {
        $counts = [];

        foreach ($this->connection->table('recruitment_applications')
            ->where('applied_at', '>=', AnalyticsDays::startUtc($day))
            ->where('applied_at', '<', AnalyticsDays::endUtc($day))
            ->groupBy('company_id', 'job_id', 'source', 'match_score')
            ->get(['company_id', 'job_id', 'source', 'match_score', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $source = $row->source === null ? 'unknown' : (string) $row->source;
            $band = MatchBand::of($row->match_score === null ? null : (int) $row->match_score)->value;
            $key = $row->job_id.'|'.$source.'|'.$band;

            $counts[$key] ??= ['day' => $day, 'company_id' => (int) $row->company_id, 'job_id' => (int) $row->job_id, 'source' => $source, 'match_band' => $band, 'applications' => 0];
            $counts[$key]['applications'] += (int) $row->total;
        }

        $this->connection->transaction(function () use ($day, $counts): void {
            $this->connection->table('recruitment_applications_daily')->where('day', $day)->delete();

            foreach (array_chunk(array_values($counts), 500) as $chunk) {
                $this->connection->table('recruitment_applications_daily')->insert($chunk);
            }
        });
    }
}
