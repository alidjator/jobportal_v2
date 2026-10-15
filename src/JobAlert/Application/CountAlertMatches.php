<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\JobAlert\AlertMatchCounts;

/** Counts the live jobs the alerts of a user match right now, whatever the cursor of each alert says. */
final readonly class CountAlertMatches implements AlertMatchCounts
{
    /** The most job ids read per alert; the same job under two alerts is counted once. */
    private const int PER_ALERT = 100;

    public function __construct(private JobAlertRepository $alerts, private JobMatcher $matcher) {}

    public function liveJobCount(int $userId): int
    {
        $ids = [];

        foreach ($this->alerts->ofUser($userId) as $alert) {
            if ($alert->paused) {
                continue;
            }

            foreach ($this->matcher->match($alert->criteria->toArray(), null, null, null, self::PER_ALERT)->jobIds as $jobId) {
                $ids[$jobId] = true;
            }
        }

        return count($ids);
    }
}
