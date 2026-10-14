<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Analytics\AnalyticsReports;
use Src\Shared\Application\Analytics\AnalyticsScope;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * One analysis over a date range, for the whole platform (an admin with `analytics.view`, which the route asks for) or for the company of a company admin. The result is
 * kept for 10 minutes; a range ends yesterday at the latest because the daily summaries are built after midnight.
 */
final readonly class GetAnalytics
{
    private const int CACHE_SECONDS = 600;

    public function __construct(
        private AnalyticsReports $reports,
        private DashboardCache $cache,
        private Settings $settings,
        private CompanyDirectory $companies,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required`, `company-suspended` or `admin-only`
     * @throws EntityNotFound the analysis does not exist for this kind of account
     * @throws BusinessRuleViolation `invalid-range`
     */
    public function execute(Actor $actor, string $report, ?string $from, ?string $to): AnalyticsReportView
    {
        [$audience, $companyId] = $this->audience($actor);
        $provider = $this->reports->find($audience, $report) ?? throw EntityNotFound::of('Analysis', $report);
        $range = AnalyticsRange::of($from, $to, $this->clock->now());
        $minGroup = $this->settings->integer(AnalyticsSettings::MIN_GROUP_SIZE);
        $scope = new AnalyticsScope($audience, $companyId, $range->from, $range->to, $minGroup);

        $built = $this->cache->remember(
            "analytics:{$audience->value}:".($companyId ?? 0).":{$report}:{$range->from}:{$range->to}:{$minGroup}",
            self::CACHE_SECONDS,
            fn (): array => [
                'generated_at' => $this->clock->now()->format('Y-m-d\TH:i:s\Z'),
                'tables' => array_map(fn ($table): array => SmallGroups::apply($table, $minGroup), $provider->build($scope)),
            ],
        );

        /** @var list<array{name: string, columns: list<string>, count_column: string|null, rows: list<array<string, mixed>>}> $tables */
        $tables = $built['tables'];

        return new AnalyticsReportView($report, $range->from, $range->to, $minGroup, $tables, (string) $built['generated_at']);
    }

    /** @return array{0: DashboardAudience, 1: int|null} */
    private function audience(Actor $actor): array
    {
        if ($actor->isAdmin()) {
            return [DashboardAudience::Admin, null];
        }

        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member or an admin has the analytics.', 'not-a-company-member');
        }

        $actor->membership->require(CompanyPermission::AnalyticsView);

        $companyId = $actor->membership->companyId;

        if (! $this->companies->isActive($companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return [DashboardAudience::Company, $companyId];
    }
}
