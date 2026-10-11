<?php

namespace Src\JobAlert\Domain;

use Src\Shared\Application\Job\LiveJobProfile;

interface JobAlertRepository
{
    /** Stores a new alert and returns it with its id. */
    public function add(JobAlert $alert): JobAlert;

    public function update(JobAlert $alert): void;

    public function findById(int $alertId): ?JobAlert;

    /** The alert, only when it belongs to this user. */
    public function findOfUser(int $userId, int $alertId, bool $forUpdate = false): ?JobAlert;

    /** @return list<JobAlert> the user's alerts, oldest first */
    public function ofUser(int $userId): array;

    /** How many alerts the user has; with `$forUpdate` the user's rows are locked, so two creations cannot both pass the limit. */
    public function countOfUser(int $userId, bool $forUpdate = false): int;

    public function delete(int $alertId): void;

    public function deleteOfUser(int $userId): void;

    /**
     * The alerts of this frequency that are not paused, with an id after `$afterId`, in id order: a batch to walk through them all.
     *
     * @return list<JobAlert>
     */
    public function activeAfter(AlertFrequency $frequency, int $afterId, int $limit): array;

    /**
     * The immediate alerts that are not paused, with an id after `$afterId`, in id order, that this live job fits: every filter of the alert holds for the job
     * and the job first went live after the alert's cursor. One statement for all alerts, so a batch needs no search per alert.
     *
     * @return list<JobAlert>
     */
    public function immediateFitting(LiveJobProfile $job, int $afterId, int $limit): array;

    /**
     * Takes one of the emails an immediate alert may send on the Jakarta day `$day` (`Y-m-d`), as one atomic step; false when the day's cap is used up.
     */
    public function reserveEmail(int $alertId, string $day): bool;
}
