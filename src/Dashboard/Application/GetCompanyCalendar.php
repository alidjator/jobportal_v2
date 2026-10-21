<?php

namespace Src\Dashboard\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Calendar\CalendarEvent;
use Src\Shared\Application\Calendar\CalendarScope;
use Src\Shared\Application\Calendar\CalendarSources;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The recruitment calendar of a company member (`docs/design/phase-8-interview-calendar.md` section 1): interviews, test due times, offer deadlines and the start dates of accepted
 * offers in a range of Jakarta days (`to` included, at most 42 days), for the jobs the member may see (every job for `jobs.view_all`, the assigned jobs for anyone else; a hiring manager
 * bound to the stage boundary of a job sees only the applications that reached it). Each context fills it through a source; this class checks the range, merges and sorts. At most
 * {@see self::LIMIT} events: beyond that the answer says it was cut and the client narrows the range or the filters. The calendar only reads.
 */
final readonly class GetCompanyCalendar
{
    public const int MAX_DAYS = 42;

    public const int LIMIT = 1000;

    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private CalendarSources $sources, private CompanyDirectory $companies, private JobDirectory $jobs, private Clock $clock) {}

    /**
     * @param  list<string>  $types  the types asked for; empty for all
     * @return array{events: list<CalendarEvent>, truncated: bool}
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws BusinessRuleViolation `invalid-calendar-range`
     */
    public function execute(Actor $actor, string $from, string $to, array $types = [], ?int $jobId = null, bool $mine = false): array
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member has a calendar.', 'not-a-company-member');
        }

        $actor->membership->require(CompanyPermission::ApplicationsView);
        $companyId = $actor->membership->companyId;

        if (! $this->companies->isActive($companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        [$start, $end] = $this->window($from, $to);
        $viewsAll = $actor->can(CompanyPermission::JobsViewAll);
        $now = $this->clock->now();

        $scope = new CalendarScope(
            $actor->id,
            $companyId,
            $viewsAll ? null : $this->jobs->assignedJobIds($companyId, $actor->id),
            $viewsAll ? [] : $this->jobs->hiringManagerJobIds($companyId, $actor->id),
            $start,
            $end,
            $types === [] ? CalendarEvent::TYPES : $types,
            $jobId,
            $mine,
            $actor->membership->effectivePermissions(),
            $now,
            self::LIMIT + 1,
        );

        $events = [];

        foreach ($this->sources->all() as $source) {
            if (array_intersect($source->types(), $scope->types) !== []) {
                array_push($events, ...$source->events($scope));
            }
        }

        usort($events, static fn (CalendarEvent $a, CalendarEvent $b): int => [$a->startsAt, $a->id] <=> [$b->startsAt, $b->id]);

        return ['events' => array_slice($events, 0, self::LIMIT), 'truncated' => count($events) > self::LIMIT];
    }

    /**
     * The window in UTC: midnight of `from` in Jakarta up to midnight after `to`.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     *
     * @throws BusinessRuleViolation `invalid-calendar-range`
     */
    private function window(string $from, string $to): array
    {
        $zone = new DateTimeZone(self::ZONE);
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone);
        $last = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone);

        if ($start === false || $last === false || $last < $start || $start->add(new DateInterval('P'.(self::MAX_DAYS - 1).'D')) < $last) {
            $message = 'The range must start on or before its last day and cover at most :max days.';

            throw new BusinessRuleViolation($message, 'invalid-calendar-range', extensions: ['errors' => ['to' => [$message]]], parameters: ['max' => self::MAX_DAYS]);
        }

        $utc = new DateTimeZone('UTC');

        return [$start->setTimezone($utc), $last->add(new DateInterval('P1D'))->setTimezone($utc)];
    }
}
