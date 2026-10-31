<?php

namespace Src\Assessment\Infrastructure\Tasks;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Tasks\ApplicantTask;
use Src\Shared\Application\Tasks\ApplicantTaskSource;

/** The tests a job seeker was sent and has not submitted (`invited` or `started`) whose due time has not passed (`assessment_take`, due at that time). */
final readonly class ApplicantAssessmentTasks implements ApplicantTaskSource
{
    public function __construct(private ConnectionInterface $connection) {}

    public function tasks(int $userId, DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->table('assessment_invitations')
            ->where('applicant_user_id', $userId)
            ->whereIn('status', ['invited', 'started'])
            ->where('due_at', '>', $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->orderBy('due_at')->orderBy('id')->limit($limit)
            ->get(['id', 'application_id', 'job_id', 'company_id', 'due_at', 'created_at']);

        return $rows->map(static fn (object $row): ApplicantTask => new ApplicantTask(
            ApplicantTask::ASSESSMENT_TAKE,
            (int) $row->id,
            (int) $row->application_id,
            (int) $row->job_id,
            (int) $row->company_id,
            new DateTimeImmutable((string) $row->due_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        ))->all();
    }
}
