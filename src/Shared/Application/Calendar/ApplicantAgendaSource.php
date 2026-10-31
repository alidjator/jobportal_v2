<?php

namespace Src\Shared\Application\Calendar;

use DateTimeImmutable;

/**
 * A context's part of the agenda of a job seeker. The context registers its sources in `ApplicantAgendaSources` from its service provider, so the Dashboard context that serves the agenda knows no other
 * context. A source reads on indexed columns and returns at most `$limit` dates of the account that overlap `[from, to)` (UTC), the earliest first; nothing of a final application or an anonymised applicant.
 */
interface ApplicantAgendaSource
{
    /** @return list<ApplicantAgendaItem> */
    public function items(int $userId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array;
}
