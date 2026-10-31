<?php

namespace Src\Dashboard\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Calendar\ApplicantAgendaItem;
use Src\Shared\Application\Calendar\ApplicantAgendaSources;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The agenda of a job seeker (`docs/design/phase-8-candidate-experience.md` section 5): interviews, test windows, offer deadlines, join dates and check schedules in a range of Jakarta days (`to` included, at most 42 days;
 * the default is today and the 29 days after). Each context fills it through a source; this class checks the range, merges, sorts and names the jobs and companies. At most {@see self::LIMIT} dates: beyond that the answer
 * says it was cut and the client narrows the range. It is read live (not cached) and only reads.
 */
final readonly class GetApplicantAgenda
{
    public const int MAX_DAYS = 42;

    public const int LIMIT = 200;

    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private ApplicantAgendaSources $sources, private JobDirectory $jobs, private CompanyDirectory $companies, private Clock $clock) {}

    /**
     * @return array{items: list<ApplicantAgendaEntry>, truncated: bool}
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `invalid-calendar-range`
     */
    public function execute(Actor $actor, ?string $from = null, ?string $to = null): array
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has this agenda.', 'job-seeker-only');
        }

        $zone = new DateTimeZone(self::ZONE);
        $today = $this->clock->now()->setTimezone($zone)->setTime(0, 0);
        $start = $from === null ? $today : DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone);
        $last = $to === null ? $today->add(new DateInterval('P29D')) : DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone);

        if ($start === false || $last === false || $last < $start || $start->add(new DateInterval('P'.(self::MAX_DAYS - 1).'D')) < $last) {
            $message = 'The range must start on or before its last day and cover at most :max days.';

            throw new BusinessRuleViolation($message, 'invalid-calendar-range', extensions: ['errors' => ['to' => [$message]]], parameters: ['max' => self::MAX_DAYS]);
        }

        $utc = new DateTimeZone('UTC');

        return $this->between($actor->id, $start->setTimezone($utc), $last->add(new DateInterval('P1D'))->setTimezone($utc));
    }

    /**
     * The dates that overlap `[from, to)`, named and sorted.
     *
     * @return array{items: list<ApplicantAgendaEntry>, truncated: bool}
     */
    public function between(int $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $items = [];

        foreach ($this->sources->all() as $source) {
            array_push($items, ...$source->items($userId, $from, $to, self::LIMIT + 1));
        }

        usort($items, static fn (ApplicantAgendaItem $a, ApplicantAgendaItem $b): int => [$a->startsAt, $a->id] <=> [$b->startsAt, $b->id]);

        $kept = array_slice($items, 0, self::LIMIT);
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (ApplicantAgendaItem $item): int => $item->jobId, $kept))));
        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (ApplicantAgendaItem $item): int => $item->companyId, $kept))));

        return [
            'items' => array_map(static fn (ApplicantAgendaItem $item): ApplicantAgendaEntry => new ApplicantAgendaEntry($item, $titles[$item->jobId] ?? null, ($companies[$item->companyId] ?? null)?->name), $kept),
            'truncated' => count($items) > self::LIMIT,
        ];
    }
}
