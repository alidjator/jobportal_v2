<?php

namespace Src\Recruitment\Infrastructure\Dashboard;

use DateInterval;
use DateTimeZone;
use Src\Recruitment\Infrastructure\Analytics\Figures;
use Src\Recruitment\Infrastructure\Analytics\FilledJob;
use Src\Recruitment\Infrastructure\Analytics\TimeToFill;
use Src\Shared\Application\Analytics\AnalyticsDays;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Settings\Settings;

/** How long the jobs filled in the last 90 days (up to yesterday, as the analyses) took, and how many live jobs are not filled yet; the figures of the `fill` analysis. */
final readonly class CompanyTimeToFillCard implements DashboardCardProvider
{
    private const int DAYS = 90;

    public function __construct(private TimeToFill $fill, private Settings $settings, private Clock $clock) {}

    public function key(): string
    {
        return 'time_to_fill';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Company];
    }

    public function permission(): string
    {
        return CompanyPermission::AnalyticsView->value;
    }

    public function build(DashboardScope $scope): array
    {
        $companyId = (int) $scope->companyId;
        $now = $this->clock->now();
        $yesterday = $now->setTimezone(new DateTimeZone('Asia/Jakarta'))->setTime(0, 0)->sub(new DateInterval('P1D'));
        $to = $yesterday->format('Y-m-d');
        $from = $yesterday->sub(new DateInterval('P'.(self::DAYS - 1).'D'))->format('Y-m-d');

        $days = array_map(
            static fn (FilledJob $filled): float => $filled->days,
            $this->fill->filled($companyId, AnalyticsDays::startUtc($from), AnalyticsDays::endUtc($to)),
        );

        return [
            'period_days' => self::DAYS,
            'filled_jobs' => count($days),
            'median_days' => Figures::median($days, $this->settings->integer('analytics.min_group_size')),
            'open_jobs' => count($this->fill->open($companyId, $now)),
        ];
    }
}
