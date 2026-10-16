<?php

namespace Src\Recruitment\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\OfferStatus;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** The next interviews of the jobs the member may see (waiting for the candidate's answer or confirmed), and how many offers still wait for an answer. */
final readonly class CompanyScheduleCard implements DashboardCardProvider
{
    private const int LIMIT = 5;

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function key(): string
    {
        return 'schedule';
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
        $jobIds = $scope->jobIds;

        $interviews = HiringManagerBound::limit($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($jobIds !== null, fn ($query) => $query->whereIn('a.job_id', $jobIds ?? []))
            ->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])
            ->where('i.starts_at', '>=', $this->clock->now()->format('Y-m-d H:i:s'))
            ->orderBy('i.starts_at')
            ->limit(self::LIMIT)
            ->get(['i.id', 'i.application_id', 'a.job_id', 'i.starts_at', 'i.format', 'i.status', 'i.interviewer_id']);

        $offers = HiringManagerBound::limit($this->connection->table('recruitment_offers as o')->join('recruitment_applications as a', 'a.id', '=', 'o.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($jobIds !== null, fn ($query) => $query->whereIn('a.job_id', $jobIds ?? []))
            ->where('o.status', OfferStatus::Pending->value)
            ->count();

        return [
            'interviews' => $interviews->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'application_id' => (int) $row->application_id,
                'job_id' => (int) $row->job_id,
                'starts_at' => DashboardWindow::iso((string) $row->starts_at),
                'format' => (string) $row->format,
                'status' => (string) $row->status,
                'interviewer_id' => (int) $row->interviewer_id,
            ])->all(),
            'offers_waiting' => $offers,
        ];
    }
}
