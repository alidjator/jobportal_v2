<?php

namespace Src\Dashboard\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Src\Dashboard\Application\AnalyticsPermissions;
use Src\Dashboard\Application\AnalyticsSettings;
use Src\Dashboard\Application\DashboardCache;
use Src\Dashboard\Application\DashboardPermissions;
use Src\Dashboard\Interface\Console\RollUpAnalyticsCommand;
use Src\Shared\Application\Analytics\AnalyticsReports;
use Src\Shared\Application\Analytics\AnalyticsRollups;
use Src\Shared\Application\Calendar\CalendarSources;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Tasks\TaskProviders;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class DashboardServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DashboardCache::class => LaravelDashboardCache::class,
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
        $this->commands([RollUpAnalyticsCommand::class]);

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...DashboardPermissions::definitions(), ...AnalyticsPermissions::definitions());
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...AnalyticsSettings::definitions());
        });

        // Every night just after midnight, Jakarta time: yesterday is summarised. The job rebuilds the day from the source tables, so a second run does no harm.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RollUpAnalyticsCommand::class)->dailyAt('00:30')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });
    }
}
