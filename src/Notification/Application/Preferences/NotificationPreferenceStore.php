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

    /** Stores the user's choice, replacing an earlier one for the same key and channel. */
    public function set(int $userId, string $key, string $channel, bool $enabled, DateTimeImmutable $at): void;
}
