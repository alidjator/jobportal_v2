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
}
