<?php

namespace Src\Recruitment\Infrastructure\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Src\Recruitment\Domain\OfferStatus;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Calendar\CalendarEvent;
use Src\Shared\Application\Calendar\CalendarScope;
use Src\Shared\Application\Calendar\CalendarSource;
use Src\Shared\Application\Job\JobDirectory;

/**
 * The offers of the calendar: the moment a pending offer can no longer be answered (`offer_deadline`) and the start date of an accepted one (`start_date`, all day, a Jakarta day). The
 * member who sent the offer is the one it involves.
 */
final readonly class OfferCalendarSource implements CalendarSource
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private ConnectionInterface $connection, private JobDirectory $jobs) {}

    public function types(): array
    {
        return [CalendarEvent::OFFER_DEADLINE, CalendarEvent::START_DATE];
    }

    public function events(CalendarScope $scope): array
    {
        if ($scope->jobIds === []) {
            return [];
        }

        $events = [];

        if ($scope->wants(CalendarEvent::OFFER_DEADLINE)) {
            $rows = $this->base($scope, OfferStatus::Pending)
                ->where('o.expires_at', '>=', $this->utc($scope->from))->where('o.expires_at', '<', $this->utc($scope->to))
                ->orderBy('o.expires_at')->orderBy('o.id')->limit($scope->limit)
                ->get(['o.id', 'o.application_id', 'o.position', 'o.expires_at as at', 'o.status', 'o.created_by', 'a.job_id', new Expression("a.profile_snapshot->>'name' as candidate_name")]);

            foreach ($rows as $row) {
                $events[] = $this->event($row, CalendarEvent::OFFER_DEADLINE, new DateTimeImmutable((string) $row->at, new DateTimeZone('UTC')), false, $scope);
            }
        }

        if ($scope->wants(CalendarEvent::START_DATE)) {
            $zone = new DateTimeZone(self::ZONE);
            $rows = $this->base($scope, OfferStatus::Accepted)
                ->where('o.start_date', '>=', $scope->from->setTimezone($zone)->format('Y-m-d'))->where('o.start_date', '<', $scope->to->setTimezone($zone)->format('Y-m-d'))
                ->orderBy('o.start_date')->orderBy('o.id')->limit($scope->limit)
                ->get(['o.id', 'o.application_id', 'o.position', 'o.start_date as at', 'o.status', 'o.created_by', 'a.job_id', new Expression("a.profile_snapshot->>'name' as candidate_name")]);

            foreach ($rows as $row) {
                $events[] = $this->event($row, CalendarEvent::START_DATE, new DateTimeImmutable(substr((string) $row->at, 0, 10).' 00:00:00', $zone), true, $scope);
            }
        }

        return $this->withTitles($events);
    }

    private function base(CalendarScope $scope, OfferStatus $status): Builder
    {
        return HiringManagerBound::limit($this->connection->table('recruitment_offers as o')->join('recruitment_applications as a', 'a.id', '=', 'o.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn (Builder $query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->when($scope->jobId !== null, fn (Builder $query) => $query->where('a.job_id', $scope->jobId))
            ->whereNotNull('a.profile_snapshot')
            ->where('o.status', $status->value)
            ->when($scope->mine, fn (Builder $query) => $query->where('o.created_by', $scope->userId));
    }

    private function event(object $row, string $type, DateTimeImmutable $startsAt, bool $allDay, CalendarScope $scope): CalendarEvent
    {
        $name = $row->candidate_name === null ? null : (string) $row->candidate_name;

        return new CalendarEvent(
            "{$type}:{$row->id}",
            $type,
            (string) $name,
            $startsAt,
            null,
            $allDay,
            (string) $row->status,
            (int) $row->application_id,
            (int) $row->job_id,
            null,
            $name,
            ['offer_id' => (int) $row->id, 'position' => (string) $row->position],
            (int) $row->created_by === $scope->userId,
            ['view_application'],
        );
    }

    /**
     * The job title goes into the title of each event, in one lookup.
     *
     * @param  list<CalendarEvent>  $events
     * @return list<CalendarEvent>
     */
    private function withTitles(array $events): array
    {
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (CalendarEvent $event): int => $event->jobId, $events))));

        return array_map(static fn (CalendarEvent $event): CalendarEvent => new CalendarEvent(
            $event->id, $event->type, trim($event->title.' - '.($titles[$event->jobId] ?? ''), ' -'), $event->startsAt, $event->endsAt, $event->allDay, $event->status, $event->applicationId,
            $event->jobId, $titles[$event->jobId] ?? null, $event->candidateName, $event->details, $event->involvesMe, $event->actions,
        ), $events);
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
