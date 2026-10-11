<?php

namespace Src\Notification\Application\Preferences;

use DateTimeImmutable;

interface NotificationPreferenceStore
{
    /**
     * The values the user has set, keyed by `key.channel` (for example `application_update.email`).
     *
     * @return array<string, bool>
     */
    public function stored(int $userId): array;

    /**
     * The values each of these users has set, keyed by user id and then by `key.channel`; a user who set nothing is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, bool>>
     */
    public function storedFor(array $userIds): array;

    /** Stores the user's choice, replacing an earlier one for the same key and channel. */
    public function set(int $userId, string $key, string $channel, bool $enabled, DateTimeImmutable $at): void;
}
