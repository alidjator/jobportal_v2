<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\JobAlert\SavedSearches;

/** Gives Job the filters of a user's alert, so it can run the search the alert stands for. */
final readonly class AlertSavedSearches implements SavedSearches
{
    public function __construct(private JobAlertRepository $alerts) {}

    public function filtersOf(int $userId, int $alertId): ?array
    {
        return $this->alerts->findOfUser($userId, $alertId)?->criteria->toArray();
    }
}
