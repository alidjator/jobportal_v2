<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

/** The state of the profile reminders: how many were sent to a job seeker and when the last one went out. */
interface ProfileReminderRepository
{
    /** The most reminders a job seeker gets in all. */
    public const int MAX_REMINDERS = 3;

    /**
     * Among these accounts, the ones that may get a reminder now: fewer than three sent, and none sent after the cutoff. An account without a profile row has had none.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function dueAmong(array $userIds, DateTimeImmutable $lastRemindedBefore): array;

    /** Counts one more reminder for the account and stamps the time; creates the empty profile row when the account never saved a profile. */
    public function markSent(int $userId, DateTimeImmutable $at): void;
}
