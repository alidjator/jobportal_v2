<?php

namespace Src\Shared\Application\Calendar;

use DateTimeImmutable;

/**
 * One entry of the recruitment calendar (`docs/design/phase-8-interview-calendar.md` section 1). `endsAt` is null for a point in time, and `allDay` marks an event that has no
 * hour (the start date of an accepted offer: `startsAt` is then midnight of that day in Jakarta). `details` holds what is specific to the type (the format and the panel of an
 * interview, the position of an offer); `actions` names what the caller may do now, each through the endpoint that already exists.
 */
final readonly class CalendarEvent
{
    public const string INTERVIEW = 'interview';

    public const string ASSESSMENT_DUE = 'assessment_due';

    public const string OFFER_DEADLINE = 'offer_deadline';

    public const string START_DATE = 'start_date';

    /** @var list<string> */
    public const array TYPES = [self::INTERVIEW, self::ASSESSMENT_DUE, self::OFFER_DEADLINE, self::START_DATE];

    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $actions
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public bool $allDay,
        public string $status,
        public int $applicationId,
        public int $jobId,
        public ?string $jobTitle,
        public ?string $candidateName,
        public array $details,
        public bool $involvesMe,
        public array $actions,
    ) {}
}
