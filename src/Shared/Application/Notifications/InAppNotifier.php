<?php

namespace Src\Shared\Application\Notifications;

/**
 * Writes in-app notifications for public user accounts. The server stores no text: a notification is a stable `type` key (for example
 * `company.document_approved`) plus structured `data`, and clients write and translate the message. Provided by Notification, used by the
 * context that produces the event. Whether a user wants email or push for a kind is a separate matter (`NotificationPreferences`); in-app
 * notifications are always written.
 */
interface InAppNotifier
{
    /**
     * Writes one notification of the same type and data for each of the users.
     *
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $data  values that can be written as JSON; never secrets or whole personal records
     */
    public function send(array $userIds, string $type, array $data = []): void;

    /**
     * Like `send`, but a user who still has an unread notification of the same type and the same value in the data field `$groupKey` gets that notification
     * updated (new data, new time) instead of another one. Used for a feed that would otherwise flood the list, such as the messages of one conversation.
     *
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $data  must hold `$groupKey`
     */
    public function refresh(array $userIds, string $type, string $groupKey, array $data): void;
}
