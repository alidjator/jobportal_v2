<?php

namespace Src\Campaign\Application;

/**
 * What a campaign brought, as counts only: the accounts registered with its code, how many of them applied, and how many applications they sent (to the jobs of the
 * campaign's company, or to any job for a platform campaign), all since the start; and the registrations of each of the last 30 Jakarta days.
 */
final readonly class CampaignResults
{
    /** @param  list<array{day: string, count: int}>  $registrationsDaily */
    public function __construct(
        public int $registrations,
        public int $applicants,
        public int $applications,
        public string $from,
        public string $to,
        public array $registrationsDaily,
    ) {}
}
