<?php

namespace Src\Recruitment\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The applications to the jobs the member may see: how many wait for a first look (still in the first stage) and how many stand in each stage. */
final readonly class CompanyApplicationsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'applications';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Company];
    }

    public function permission(): string
    {
        return CompanyPermission::ApplicationsView->value;
    }

    public function build(DashboardScope $scope): array
    {
        $byStage = array_fill_keys(array_map(fn (StageType $type): string => $type->value, StageType::cases()), 0);

        foreach (HiringManagerBound::limit($this->connection->table('recruitment_applications as a'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->groupBy('stage_type')
            ->get(['stage_type', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $byStage[(string) $row->stage_type] = (int) $row->total;
        }

        return ['new' => $byStage[StageType::Applied->value], 'by_stage' => $byStage];
    }
}
