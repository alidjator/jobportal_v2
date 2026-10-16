<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\StageClock;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Applications that have waited longer than the company allows in `applied` or `screening` (the same rule as the `stale` mark of the lists). */
final readonly class StaleApplicationsTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection, private Settings $settings) {}

    public function key(): string
    {
        return 'stale_applications';
    }

    public function order(): int
    {
        return 10;
    }

    public function permission(): string
    {
        return CompanyPermission::ApplicationsView->value;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/applications?filter[stale]=1';
    }

    public function count(TaskScope $scope): int
    {
        $limit = $this->settings->integer(RecruitmentSettings::STALE_AFTER_DAYS, $scope->companyId);
        $cutoff = $scope->now->setTimezone(new DateTimeZone(StageClock::ZONE))->modify("-{$limit} days")->format('Y-m-d');

        return HiringManagerBound::limit($this->connection->table('recruitment_applications as a'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->whereIn('a.stage_type', [StageType::Applied->value, StageType::Screening->value])
            ->whereRaw("(a.stage_entered_at AT TIME ZONE 'UTC' AT TIME ZONE ?)::date < ?::date", [StageClock::ZONE, $cutoff])
            ->count();
    }
}
