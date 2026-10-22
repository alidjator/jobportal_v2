<?php

namespace Src\Recruitment\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\OfferStatus;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;
use Src\Shared\Application\Job\JobDirectory;

/**
 * The next interviews of the jobs the member may see (waiting for the candidate's answer or confirmed) with the candidate's name and the job's title, and how many
 * offers still wait for an answer. A candidate whose account was deleted shows as "Deleted user", as in the interview comparison.
 */
final readonly class CompanyScheduleCard implements DashboardCardProvider
{
    private const int LIMIT = 5;

    public function __construct(private ConnectionInterface $connection, private Clock $clock, private JobDirectory $jobs) {}

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
            ->get([
                'i.id', 'i.application_id', 'a.job_id', 'i.starts_at', 'i.format', 'i.status', 'i.interviewer_id',
                new Expression('a.profile_snapshot is null as candidate_deleted'), new Expression("a.profile_snapshot->>'name' as candidate_name"),
            ]);
        $titles = $this->jobs->titles($interviews->pluck('job_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());

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
                'job_title' => $titles[(int) $row->job_id] ?? '',
                'candidate_name' => $row->candidate_deleted ? 'Deleted user' : (string) $row->candidate_name,
                'starts_at' => DashboardWindow::iso((string) $row->starts_at),
                'format' => (string) $row->format,
                'status' => (string) $row->status,
                'interviewer_id' => (int) $row->interviewer_id,
            ])->all(),
            'offers_waiting' => $offers,
        ];
    }
}
