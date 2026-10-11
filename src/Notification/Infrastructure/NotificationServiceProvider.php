<?php

namespace Src\Notification\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Notification\Application\DeviceTokens\DeviceTokenRepository;
use Src\Notification\Application\InApp\InAppNotificationReader;
use Src\Notification\Application\InApp\InAppNotificationRepository;
use Src\Notification\Application\Preferences\NotificationPreferenceStore;
use Src\Notification\Application\Preferences\StoredNotificationPreferences;
use Src\Notification\Application\PurgeUserNotificationData;
use Src\Notification\Application\UserNotificationDataPurger;
use Src\Notification\Infrastructure\Dashboard\UnreadNotificationsCard;
use Src\Notification\Infrastructure\Listeners\PurgeNotificationDataWhenUserAnonymized;
use Src\Notification\Infrastructure\Persistence\DatabaseDeviceTokenRepository;
use Src\Notification\Infrastructure\Persistence\DatabaseInAppNotificationReader;
use Src\Notification\Infrastructure\Persistence\DatabaseInAppNotificationRepository;
use Src\Notification\Infrastructure\Persistence\DatabaseInAppNotifier;
use Src\Notification\Infrastructure\Persistence\DatabaseNotificationPreferenceStore;
use Src\Notification\Infrastructure\Persistence\DatabaseUserNotificationDataPurger;
use Src\Notification\Interface\Console\PurgeNotificationsCommand;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class NotificationServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserNotificationDataPurger::class => DatabaseUserNotificationDataPurger::class,
        InAppNotificationReader::class => DatabaseInAppNotificationReader::class,
        InAppNotificationRepository::class => DatabaseInAppNotificationRepository::class,
        DeviceTokenRepository::class => DatabaseDeviceTokenRepository::class,
        NotificationPreferenceStore::class => DatabaseNotificationPreferenceStore::class,
        NotificationPreferences::class => StoredNotificationPreferences::class,
        InAppNotifier::class => DatabaseInAppNotifier::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(UnreadNotificationsCard::class),
            );
        });

        $this->commands([PurgeNotificationsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeNotificationsCommand::class)->dailyAt('03:30')->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        $this->app->tag(PurgeUserNotificationData::class, ErasureStep::class);
        Event::listen(UserAnonymized::class, PurgeNotificationDataWhenUserAnonymized::class);

        parent::boot();
    }
}
