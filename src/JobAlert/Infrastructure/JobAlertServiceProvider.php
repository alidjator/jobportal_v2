<?php

namespace Src\JobAlert\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\JobAlert\Application\AlertEmailSender;
use Src\JobAlert\Application\AlertSavedSearches;
use Src\JobAlert\Application\JobAlertEmailTemplates;
use Src\JobAlert\Application\JobAlertNotificationType;
use Src\JobAlert\Application\JobAlertSettings;
use Src\JobAlert\Domain\AlertDeliveryRepository;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\JobAlert\Infrastructure\Listeners\EraseJobAlertsWhenUserAnonymized;
use Src\JobAlert\Infrastructure\Listeners\NotifyAlertsOfLiveJob;
use Src\JobAlert\Infrastructure\Mail\MailAlertSender;
use Src\JobAlert\Infrastructure\Persistence\DatabaseAlertDeliveryRepository;
use Src\JobAlert\Infrastructure\Persistence\DatabaseJobAlertRepository;
use Src\JobAlert\Interface\Console\SendJobAlertDigestsCommand;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\JobAlert\SavedSearches;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobAlertServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        JobAlertRepository::class => DatabaseJobAlertRepository::class,
        AlertDeliveryRepository::class => DatabaseAlertDeliveryRepository::class,
        AlertEmailSender::class => MailAlertSender::class,
        SavedSearches::class => AlertSavedSearches::class,
    ];

    public function register(): void
    {
        $this->commands([SendJobAlertDigestsCommand::class]);

        // Once a day at 08:00 Jakarta time; the weekly summaries are due on Mondays only, which the use case decides.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendJobAlertDigestsCommand::class)->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...JobAlertSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...JobAlertEmailTemplates::definitions());
        });

        // Email only: in-app notifications are always written.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                JobAlertNotificationType::EMAIL_PREFERENCE,
                'Email about new jobs that match one of your job alerts.',
                [NotificationChannel::Email->value => true],
            ));
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(JobStatusChanged::class, NotifyAlertsOfLiveJob::class);
        Event::listen(JobDecided::class, NotifyAlertsOfLiveJob::class);
        Event::listen(UserAnonymized::class, EraseJobAlertsWhenUserAnonymized::class);
    }
}
