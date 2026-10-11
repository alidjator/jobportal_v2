<?php

namespace Src\Shared\Application\Notifications;

/**
 * Asked by contexts before they send an email or a push notification: has this user left that kind of notification on for the
 * channel? The user's own choice wins, otherwise the default from the catalog. Asking for an unregistered key or a channel the key
 * does not support is a programming error and throws.
 */
interface NotificationPreferences
{
    public function isEnabled(int $userId, string $key, NotificationChannel $channel): bool;

    /**
     * Among these users, the ones who have the notification on for the channel; the same rule as `isEnabled`, with one read for the whole list.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function enabledAmong(array $userIds, string $key, NotificationChannel $channel): array;
}
