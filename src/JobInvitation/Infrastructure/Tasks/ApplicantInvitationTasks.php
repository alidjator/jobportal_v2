<?php

namespace Src\JobInvitation\Infrastructure\Tasks;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobInvitation\Domain\InvitationStatus;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Tasks\ApplicantTask;
use Src\Shared\Application\Tasks\ApplicantTaskSource;

/** The invitations to apply that a job seeker has not answered (`sent` or `viewed`) while the job is still live (`job_invitation`, no due time). */
final readonly class ApplicantInvitationTasks implements ApplicantTaskSource
{
    public function __construct(private ConnectionInterface $connection, private JobDirectory $jobs) {}

    public function tasks(int $userId, DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->table('jobinvitation_invitations')
            ->where('user_id', $userId)
            ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)
            ->get(['id', 'job_id', 'company_id', 'created_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $live = array_flip($this->jobs->liveJobIds($rows->pluck('job_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all()));

        return $rows->filter(static fn (object $row): bool => isset($live[(int) $row->job_id]))->map(static fn (object $row): ApplicantTask => new ApplicantTask(
            ApplicantTask::JOB_INVITATION,
            (int) $row->id,
            null,
            (int) $row->job_id,
            (int) $row->company_id,
            null,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        ))->values()->all();
    }
}
