<?php

namespace Src\Recruitment\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;
use Src\Shared\Application\Job\JobDirectory;

/** The job seeker's applications: how many stand in each stage, how many are still running (not yet hired, rejected or withdrawn), and the five latest. */
final readonly class ApplicantApplicationsCard implements DashboardCardProvider
{
    private const int RECENT = 5;

    public function __construct(private ConnectionInterface $connection, private JobDirectory $jobs) {}

    public function key(): string
    {
        return 'applications';
    }

    public function audiences(): array
    {
        return [DashboardAudience::JobSeeker];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        $byStage = array_fill_keys(array_map(fn (StageType $type): string => $type->value, StageType::cases()), 0);

        foreach ($this->connection->table('recruitment_applications')
            ->where('user_id', $scope->userId)
            ->groupBy('stage_type')
            ->get(['stage_type', $this->connection->raw('COUNT(*) as total')]) as $row) {
            $byStage[(string) $row->stage_type] = (int) $row->total;
        }

        $rows = $this->connection->table('recruitment_applications')
            ->where('user_id', $scope->userId)
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->limit(self::RECENT)
            ->get(['id', 'job_id', 'stage_type', 'applied_at']);
        $titles = $this->jobs->titles($rows->pluck('job_id')->map(fn (mixed $id): int => (int) $id)->all());

        return [
            'by_stage' => $byStage,
            'active' => array_sum(array_map(fn (StageType $type): int => $byStage[$type->value], [StageType::Applied, StageType::Screening, StageType::Interview, StageType::Offer])),
            'recent' => $rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'job_id' => (int) $row->job_id,
                'job_title' => $titles[(int) $row->job_id] ?? null,
                'stage_type' => (string) $row->stage_type,
                'applied_at' => DashboardWindow::iso((string) $row->applied_at),
            ])->all(),
        ];
    }
}
