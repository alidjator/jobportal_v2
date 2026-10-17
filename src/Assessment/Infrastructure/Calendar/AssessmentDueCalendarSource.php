<?php

namespace Src\Assessment\Infrastructure\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Calendar\CalendarEvent;
use Src\Shared\Application\Calendar\CalendarScope;
use Src\Shared\Application\Calendar\CalendarSource;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\ApplicationVisibility;

/**
 * The due times of the tests that were sent and not yet submitted (`invited` or `started`): a point in time on the calendar. A member bound to the stage boundary of a job sees only the
 * applications that reached it, and an applicant whose account was anonymised does not appear. The member who sent the test, or who scores it, is the one it involves.
 */
final readonly class AssessmentDueCalendarSource implements CalendarSource
{
    public function __construct(
        private ConnectionInterface $connection,
        private ApplicantContacts $applicants,
        private ApplicationVisibility $visibility,
        private JobDirectory $jobs,
    ) {}

    public function types(): array
    {
        return [CalendarEvent::ASSESSMENT_DUE];
    }

    public function events(CalendarScope $scope): array
    {
        if (! $scope->wants(CalendarEvent::ASSESSMENT_DUE) || $scope->jobIds === []) {
            return [];
        }

        $rows = $this->connection->table('assessment_invitations as i')
            ->where('i.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn (Builder $query) => $query->whereIn('i.job_id', $scope->jobIds ?? []))
            ->when($scope->jobId !== null, fn (Builder $query) => $query->where('i.job_id', $scope->jobId))
            ->whereIn('i.status', ['invited', 'started'])
            ->where('i.due_at', '>=', $this->utc($scope->from))->where('i.due_at', '<', $this->utc($scope->to))
            ->when($scope->mine, fn (Builder $query) => $query->where(fn (Builder $mine) => $mine->where('i.invited_by_user_id', $scope->userId)->orWhereExists(
                fn (Builder $assessor) => $assessor->selectRaw('1')->from('assessment_invitation_assessors as s')->whereColumn('s.invitation_id', 'i.id')->where('s.user_id', $scope->userId),
            )))
            ->orderBy('i.due_at')->orderBy('i.id')
            ->limit($scope->limit)
            ->get(['i.id', 'i.application_id', 'i.job_id', 'i.applicant_user_id', 'i.due_at', 'i.status', 'i.invited_by_user_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $visible = array_flip($this->visibility->visibleAmong($rows->pluck('application_id')->map(static fn (mixed $id): int => (int) $id)->all(), $scope->boundJobIds));
        $names = $this->applicants->names($rows->pluck('applicant_user_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all());
        $titles = $this->jobs->titles($rows->pluck('job_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all());
        $assessors = $this->assessorsAmong($rows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(), $scope->userId);
        $events = [];

        foreach ($rows as $row) {
            $name = $names[(int) $row->applicant_user_id] ?? null;

            if ($name === null || ! isset($visible[(int) $row->application_id])) {
                continue;
            }

            $jobId = (int) $row->job_id;
            $title = $titles[$jobId] ?? null;
            $events[] = new CalendarEvent(
                "assessment_due:{$row->id}",
                CalendarEvent::ASSESSMENT_DUE,
                trim($name.' - '.($title ?? ''), ' -'),
                new DateTimeImmutable((string) $row->due_at, new DateTimeZone('UTC')),
                null,
                false,
                (string) $row->status,
                (int) $row->application_id,
                $jobId,
                $title,
                $name,
                ['invitation_id' => (int) $row->id],
                (int) $row->invited_by_user_id === $scope->userId || isset($assessors[(int) $row->id]),
                ['view_application'],
            );
        }

        return $events;
    }

    /**
     * The invitations among these that the member scores.
     *
     * @param  list<int>  $invitationIds
     * @return array<int, true>
     */
    private function assessorsAmong(array $invitationIds, int $userId): array
    {
        $found = [];

        foreach ($this->connection->table('assessment_invitation_assessors')->whereIn('invitation_id', $invitationIds)->where('user_id', $userId)->pluck('invitation_id') as $id) {
            $found[(int) $id] = true;
        }

        return $found;
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
