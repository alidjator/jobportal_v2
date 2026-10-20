<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Analytics\AnalyticsScope;

/**
 * The medians of durations over a range, computed by the database from the stage history (a median cannot be added up from the daily summary). A median over fewer
 * than the smallest group the scope may show is null. The range is capped by the caller and the result is cached by the Dashboard context.
 *
 * The durations follow the same rules as `TransitionRows`, which feeds the daily summary: the time in a stage runs from the previous history entry of the application
 * (its submission for the first move), the initial entry is not a move, and a negative duration counts as zero.
 */
final readonly class LiveMedians
{
    private const array MILESTONES = ['offer', 'hired'];

    public function __construct(private ConnectionInterface $connection) {}

    /**
     * @return array{in_stage: array<string, float|null>, since_applied: array<string, float|null>} in days: by the stage that was left, and by `offer` and `hired` for
     *                                                                                              the time from the application to reaching them
     */
    public function of(AnalyticsScope $scope): array
    {
        $inStage = [];
        $sinceApplied = array_fill_keys(self::MILESTONES, null);

        foreach ($this->rows($scope) as $row) {
            $days = $row->count >= $scope->minGroup ? round((float) $row->median / 86400, 2) : null;

            if ($row->kind === 'in_stage') {
                $inStage[(string) $row->type] = $days;
            } else {
                $sinceApplied[(string) $row->type] = $days;
            }
        }

        return ['in_stage' => $inStage, 'since_applied' => $sinceApplied];
    }

    /** @return list<object{kind: string, type: string, count: int, median: float}> */
    private function rows(AnalyticsScope $scope): array
    {
        $milestones = implode(', ', array_fill(0, count(self::MILESTONES), '?'));
        $company = $scope->companyId === null ? '' : 'AND a.company_id = ?';

        // The previous entry is found over the whole history of an application, then the range is applied, so a move at the start of the range still sees its predecessor.
        $sql = <<<SQL
            WITH moves AS (
                SELECT h.from_type, h.to_type, h.created_at, a.applied_at,
                    LAG(h.created_at) OVER (PARTITION BY h.application_id ORDER BY h.id) AS previous_at
                FROM recruitment_application_stage_history AS h
                JOIN recruitment_applications AS a ON a.id = h.application_id
                WHERE a.migrated_at IS NULL {$company}
            ), durations AS (
                SELECT from_type, to_type,
                    GREATEST(0, EXTRACT(EPOCH FROM date_trunc('second', created_at)) - EXTRACT(EPOCH FROM date_trunc('second', COALESCE(previous_at, applied_at)))) AS in_stage,
                    GREATEST(0, EXTRACT(EPOCH FROM date_trunc('second', created_at)) - EXTRACT(EPOCH FROM date_trunc('second', applied_at))) AS since_applied
                FROM moves
                WHERE from_type IS NOT NULL AND created_at >= ? AND created_at < ?
            )
            SELECT 'in_stage' AS kind, from_type AS type, COUNT(*) AS count, percentile_cont(0.5) WITHIN GROUP (ORDER BY in_stage) AS median
            FROM durations GROUP BY from_type
            UNION ALL
            SELECT 'since_applied', to_type, COUNT(*), percentile_cont(0.5) WITHIN GROUP (ORDER BY since_applied)
            FROM durations WHERE to_type IN ({$milestones}) GROUP BY to_type
            SQL;

        $bindings = [
            ...($scope->companyId === null ? [] : [$scope->companyId]),
            AnalyticsDays::startUtc($scope->from),
            AnalyticsDays::endUtc($scope->to),
            ...self::MILESTONES,
        ];

        return $this->connection->select($sql, $bindings);
    }
}
