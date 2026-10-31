<?php

namespace Src\Shared\Application\Calendar;

use DateTimeImmutable;

/**
 * One date of the agenda of a job seeker (`docs/design/phase-8-candidate-experience.md` section 5). `endsAt` is null for a point in time; `allDay` marks a date with no hour (the join date: `startsAt` is then midnight of
 * that day in Jakarta). `details` holds what is specific to the type and never an outcome or a note of the team.
 */
final readonly class ApplicantAgendaItem
{
    public const string INTERVIEW = 'interview';

    public const string OFFER_DEADLINE = 'offer_deadline';

    public const string JOIN_DATE = 'join_date';

    public const string ASSESSMENT_WINDOW = 'assessment_window';

    public const string CHECK = 'check';

    /** @var list<string> */
    public const array TYPES = [self::INTERVIEW, self::OFFER_DEADLINE, self::JOIN_DATE, self::ASSESSMENT_WINDOW, self::CHECK];

    /** @param  array<string, mixed>  $details */
    public function __construct(
        public string $id,
        public string $type,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public bool $allDay,
        public string $status,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public array $details,
    ) {}
}
