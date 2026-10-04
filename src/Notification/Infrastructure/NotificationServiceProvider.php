<?php

namespace Src\Notification\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Notification\Application\UserNotificationDataPurger;
use Src\Notification\Infrastructure\Listeners\PurgeNotificationDataWhenUserAnonymized;
use Src\Notification\Infrastructure\Persistence\DatabaseUserNotificationDataPurger;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class NotificationServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserNotificationDataPurger::class => DatabaseUserNotificationDataPurger::class,
    ];

    public function boot(): void
    {
        Event::listen(UserAnonymized::class, PurgeNotificationDataWhenUserAnonymized::class);

        parent::boot();
    }
}
