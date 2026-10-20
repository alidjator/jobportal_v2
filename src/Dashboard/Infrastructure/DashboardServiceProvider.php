<?php

namespace Src\Dashboard\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Src\Dashboard\Application\AnalyticsPermissions;
use Src\Dashboard\Application\AnalyticsSettings;
use Src\Dashboard\Application\DashboardCache;
use Src\Dashboard\Application\DashboardPermissions;
use Src\Dashboard\Application\SendTeamDigests;
use Src\Dashboard\Application\TeamDigestEmailTemplates;
use Src\Dashboard\Application\TeamDigestSender;
use Src\Dashboard\Infrastructure\Mail\MailTeamDigestSender;
use Src\Dashboard\Interface\Console\RollUpAnalyticsCommand;
use Src\Dashboard\Interface\Console\SendTeamDigestsCommand;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Analytics\AnalyticsReports;
use Src\Shared\Application\Analytics\AnalyticsRollups;
use Src\Shared\Application\Calendar\CalendarSources;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Tasks\TaskProviders;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class DashboardServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DashboardCache::class => LaravelDashboardCache::class,
        TeamDigestSender::class => MailTeamDigestSender::class,
    ];

    /** @var array<class-string, class-string> One list of cards, analyses and summaries for the whole application; every context adds its own. */
    public array $singletons = [
        DashboardCards::class => DashboardCards::class,
        TaskProviders::class => TaskProviders::class,
        CalendarSources::class => CalendarSources::class,
        AnalyticsReports::class => AnalyticsReports::class,
        AnalyticsRollups::class => AnalyticsRollups::class,
    ];

    public function register(): void
    {
        $this->commands([RollUpAnalyticsCommand::class, SendTeamDigestsCommand::class]);

        // The links of the digest start at the company app; empty means the digest carries no links.
        $this->app->when(SendTeamDigests::class)->needs('$companyAppUrl')->giveConfig('app.company_app_url');

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...TeamDigestEmailTemplates::definitions());
        });

        // Email only: the in-app task list is always there.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                SendTeamDigests::PREFERENCE,
                'Email every morning (Monday to Saturday) with what waits for you: your task list, new applications on your jobs and your interviews of the day. Nothing is sent when nothing waits.',
                [NotificationChannel::Email->value => true],
                [ActorKind::CompanyMember->value],
                scheduled: true,
            ));
        });

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...DashboardPermissions::definitions(), ...AnalyticsPermissions::definitions());
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...AnalyticsSettings::definitions());
        });

        // Every night just after midnight, Jakarta time: yesterday is summarised. The job rebuilds the day from the source tables, so a second run does no harm.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RollUpAnalyticsCommand::class)->dailyAt('00:30')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();

            // Every morning at 07:00 Jakarta time, Monday to Saturday.
            $schedule->command(SendTeamDigestsCommand::class)->weeklyOn([1, 2, 3, 4, 5, 6], '07:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });
    }
}
