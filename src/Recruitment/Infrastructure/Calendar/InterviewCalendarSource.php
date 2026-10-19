<?php

namespace Src\Recruitment\Infrastructure\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\RsvpAnswer;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Calendar\CalendarEvent;
use Src\Shared\Application\Calendar\CalendarScope;
use Src\Shared\Application\Calendar\CalendarSource;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;

/**
 * The interviews of the calendar: pending, confirmed, completed and no-show ones that overlap the window (declined and cancelled ones are not shown), for the jobs the member may see, without
 * applications whose applicant was anonymised. The link of the meeting stays in the interview detail: the event only says whether there is one.
 */
final readonly class InterviewCalendarSource implements CalendarSource
{
    public function __construct(private ConnectionInterface $connection, private JobDirectory $jobs, private CompanyMemberAccounts $accounts, private InterviewRsvps $rsvps) {}

    public function types(): array
    {
        return [CalendarEvent::INTERVIEW];
    }

    public function events(CalendarScope $scope): array
    {
        if (! $scope->wants(CalendarEvent::INTERVIEW) || $scope->jobIds === []) {
            return [];
        }

        $rows = HiringManagerBound::limit($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn (Builder $query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->when($scope->jobId !== null, fn (Builder $query) => $query->where('a.job_id', $scope->jobId))
            ->whereNotNull('a.profile_snapshot')
            ->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value, InterviewStatus::Completed->value, InterviewStatus::NoShow->value])
            ->where('i.starts_at', '<', $this->utc($scope->to))
            ->where('i.ends_at', '>', $this->utc($scope->from))
            ->when($scope->mine, fn (Builder $query) => $query->where(fn (Builder $mine) => $mine->where('i.interviewer_id', $scope->userId)->orWhereExists(
                fn (Builder $panel) => $panel->selectRaw('1')->from('recruitment_interview_participants as p')->whereColumn('p.interview_id', 'i.id')->where('p.user_id', $scope->userId),
            )))
            ->orderBy('i.starts_at')->orderBy('i.id')
            ->limit($scope->limit)
            ->get(['i.id', 'i.application_id', 'a.job_id', 'i.starts_at', 'i.ends_at', 'i.status', 'i.format', 'i.location', 'i.location_id', 'i.meeting_url', 'i.interviewer_id', 'i.proposed_starts_at', new Expression("a.profile_snapshot->>'name' as candidate_name")]);

        if ($rows->isEmpty()) {
            return [];
        }

        $panels = [];

        foreach ($this->connection->table('recruitment_interview_participants')->whereIn('interview_id', $rows->pluck('id')->all())->orderBy('id')->get(['interview_id', 'user_id']) as $participant) {
            $panels[(int) $participant->interview_id][] = (int) $participant->user_id;
        }

        $answers = $this->rsvps->forInterviews($rows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
        $userIds = $rows->pluck('interviewer_id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($panels as $members) {
            $userIds = [...$userIds, ...$members];
        }

        $names = [];

        foreach ($this->accounts->summaries(array_values(array_unique($userIds))) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        $titles = $this->jobs->titles($rows->pluck('job_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all());

        return $rows->map(function (object $row) use ($scope, $panels, $names, $titles, $answers): CalendarEvent {
            $id = (int) $row->id;
            $interviewerId = (int) $row->interviewer_id;
            $panel = $panels[$id] ?? [];
            $jobId = (int) $row->job_id;
            $title = $titles[$jobId] ?? null;
            $name = $row->candidate_name === null ? null : (string) $row->candidate_name;
            $involved = $interviewerId === $scope->userId || in_array($scope->userId, $panel, true);
            $startsAt = $this->time((string) $row->starts_at);
            $status = (string) $row->status;
            $proposed = $row->proposed_starts_at === null ? null : $this->time((string) $row->proposed_starts_at);

            return new CalendarEvent(
                "interview:{$id}",
                CalendarEvent::INTERVIEW,
                trim(($name ?? '').' - '.($title ?? ''), ' -'),
                $startsAt,
                $this->time((string) $row->ends_at),
                false,
                $status,
                (int) $row->application_id,
                $jobId,
                $title,
                $name,
                [
                    'interview_id' => $id,
                    'format' => (string) $row->format,
                    'location' => $row->location === null ? null : (string) $row->location,
                    'location_id' => $row->location_id === null ? null : (int) $row->location_id,
                    'has_meeting_link' => $row->meeting_url !== null,
                    'interviewer' => ['id' => $interviewerId, 'name' => $names[$interviewerId] ?? null, 'rsvp' => ($answers[$id][$interviewerId] ?? RsvpAnswer::pending())->rsvp->value],
                    'panel' => array_map(static fn (int $userId): array => ['id' => $userId, 'name' => $names[$userId] ?? null, 'rsvp' => ($answers[$id][$userId] ?? RsvpAnswer::pending())->rsvp->value], $panel),
                    /** The answer of the member who asks to the invitation; null when they do not sit on the panel. */
                    'my_rsvp' => $involved ? ($answers[$id][$scope->userId] ?? RsvpAnswer::pending())->rsvp->value : null,
                    /** The time the job seeker asked for, waiting for the team's answer ("Kandidat minta pindah jadwal"); null when none. */
                    'proposed_starts_at' => $proposed?->format(DATE_ATOM),
                ],
                $involved,
                $this->actions($scope, $status, $startsAt, $involved, $proposed !== null, $interviewerId === $scope->userId),
            );
        })->all();
    }

    /**
     * What the member may do with the interview now, by the rules of the use cases that already exist: reschedule, change the note, cancel and answer a proposal need
     * `interviews.manage` and an active interview; a no-show is marked on a confirmed interview after its start, also by the main interviewer; the scorecard belongs to
     * the panel once a confirmed or completed interview has started.
     *
     * @return list<string>
     */
    private function actions(CalendarScope $scope, string $status, DateTimeImmutable $startsAt, bool $involved, bool $hasProposal, bool $leads): array
    {
        $actions = ['view_application'];
        $active = in_array($status, [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value], true);
        $manages = $scope->can(CompanyPermission::InterviewsManage->value);

        if ($active && $manages) {
            array_push($actions, 'edit', 'edit_note', 'cancel');
        }

        if ($active && $manages && $hasProposal) {
            array_push($actions, 'accept_proposal', 'refuse_proposal');
        }

        if ($status === InterviewStatus::Confirmed->value && $startsAt <= $scope->now && ($manages || $leads)) {
            $actions[] = 'no_show';
        }

        if ($involved && in_array($status, [InterviewStatus::Confirmed->value, InterviewStatus::Completed->value], true) && $startsAt <= $scope->now) {
            $actions[] = 'scorecard';
        }

        $actions[] = 'download_ics';

        return $actions;
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
