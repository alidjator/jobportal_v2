<?php

namespace Src\Shared\Application\Notifications;

/**
 * Writes in-app notifications for platform admins (the Console bell). The server stores a `type` and its data and no text, as for public users; the Console writes the message.
 * Provided by Notification.
 */
interface AdminInAppNotifier
{
    /**
     * Writes one notification of the same type and data for each active admin that holds the permission, directly or through a role. Nothing is written when nobody holds it.
     *
     * @param  array<string, mixed>  $data  values that can be written as JSON; never secrets or whole personal records
     */
    public function sendToHolders(string $permission, string $type, array $data = []): void;
}
