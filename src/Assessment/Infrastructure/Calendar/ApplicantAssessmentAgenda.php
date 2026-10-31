<?php

namespace Src\Assessment\Infrastructure\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Calendar\ApplicantAgendaItem;
use Src\Shared\Application\Calendar\ApplicantAgendaSource;

/** The window in which a job seeker may take a test they were sent and have not submitted (`invited` or `started`): from its start to its due time. */
final readonly class ApplicantAssessmentAgenda implements ApplicantAgendaSource
{
    public function __construct(private ConnectionInterface $connection) {}

    public function items(int $userId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
    {
        $utc = new DateTimeZone('UTC');

        $rows = $this->connection->table('assessment_invitations')
            ->where('applicant_user_id', $userId)
            ->whereIn('status', ['invited', 'started'])
            ->where('starts_at', '<', $to->setTimezone($utc)->format('Y-m-d H:i:s'))
            ->where('due_at', '>', $from->setTimezone($utc)->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')->orderBy('id')->limit($limit)
            ->get(['id', 'application_id', 'job_id', 'company_id', 'starts_at', 'due_at', 'status']);

        return $rows->map(static fn (object $row): ApplicantAgendaItem => new ApplicantAgendaItem(
            "assessment_window:{$row->id}",
            ApplicantAgendaItem::ASSESSMENT_WINDOW,
            new DateTimeImmutable((string) $row->starts_at, $utc),
            new DateTimeImmutable((string) $row->due_at, $utc),
            false,
            (string) $row->status,
            (int) $row->application_id,
            (int) $row->job_id,
            (int) $row->company_id,
            ['invitation_id' => (int) $row->id],
        ))->all();
    }
}
