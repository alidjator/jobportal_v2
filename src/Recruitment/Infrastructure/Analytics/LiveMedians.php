<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Analytics\AnalyticsScope;

/**
 * The medians of durations over a range, read live from the stage history (a median cannot be added up from the daily summary). A median over fewer than the smallest
 * group the scope may show is null. The range is capped by the caller and the result is cached by the Dashboard context.
 */
final readonly class LiveMedians
{
    public function __construct(private TransitionRows $transitions) {}

    /**
     * @return array{in_stage: array<string, float|null>, since_applied: array<string, float|null>} in days: by the stage that was left, and by `offer` and `hired` for
     *                                                                                              the time from the application to reaching them
     */
    public function of(AnalyticsScope $scope): array
    {
        $inStage = [];
        $sinceApplied = ['offer' => [], 'hired' => []];

        foreach ($this->transitions->between(AnalyticsDays::startUtc($scope->from), AnalyticsDays::endUtc($scope->to), $scope->companyId) as $row) {
            $inStage[$row['from_type']][] = $row['in_stage'];

            if (isset($sinceApplied[$row['to_type']])) {
                $sinceApplied[$row['to_type']][] = $row['since_applied'];
            }
        }

        return [
            'in_stage' => array_map(fn (array $seconds): ?float => $this->median($seconds, $scope->minGroup), $inStage),
            'since_applied' => array_map(fn (array $seconds): ?float => $this->median($seconds, $scope->minGroup), $sinceApplied),
        ];
    }

    /** @param  list<int>  $seconds */
    private function median(array $seconds, int $minGroup): ?float
    {
        $count = count($seconds);

        if ($count === 0 || $count < $minGroup) {
            return null;
        }

        sort($seconds);
        $middle = intdiv($count, 2);
        $value = $count % 2 === 1 ? $seconds[$middle] : ($seconds[$middle - 1] + $seconds[$middle]) / 2;

        return round($value / 86400, 2);
    }
}
