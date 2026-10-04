<?php

namespace Src\Shared\Application\Notifications;

/** The channels a user can switch on or off. In-app notifications are always delivered and have no preference. */
enum NotificationChannel: string
{
    case Email = 'email';
    case Push = 'push';
}
