<?php

namespace Src\Shared\Application\JobAlert;

/** Lets Job open the results of a job seeker's job alert without reading JobAlert's tables. Provided by JobAlert. */
interface SavedSearches
{
    /**
     * The search filters of the alert, or null when there is no such alert for this user (missing or someone else's).
     *
     * @return array<string, mixed>|null
     */
    public function filtersOf(int $userId, int $alertId): ?array;
}
