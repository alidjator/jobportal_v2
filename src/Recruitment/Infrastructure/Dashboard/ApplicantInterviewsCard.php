<?php

namespace Src\Recruitment\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** The next interviews of the job seeker (waiting for their answer or confirmed, not yet started), nearest first. */
final readonly class ApplicantInterviewsCard implements DashboardCardProvider
{
    private const int LIMIT = 3;

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function key(): string
    {
        return 'interviews';
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
        $rows = $this->connection->table('recruitment_interviews as i')
            ->join('recruitment_applications as a', 'a.id', '=', 'i.application_id')
            ->where('a.user_id', $scope->userId)
            ->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])
            ->where('i.starts_at', '>=', $this->clock->now()->format('Y-m-d H:i:s'))
            ->orderBy('i.starts_at')
            ->limit(self::LIMIT)
            ->get(['i.id', 'i.application_id', 'a.job_id', 'i.starts_at', 'i.format', 'i.status']);

        return [
            'upcoming' => $rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'application_id' => (int) $row->application_id,
                'job_id' => (int) $row->job_id,
                'starts_at' => DashboardWindow::iso((string) $row->starts_at),
                'format' => (string) $row->format,
                'status' => (string) $row->status,
            ])->all(),
        ];
    }
}
