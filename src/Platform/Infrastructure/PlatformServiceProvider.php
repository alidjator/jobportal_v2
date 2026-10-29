<?php

namespace Src\Platform\Infrastructure;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Src\Platform\Application\Audit\AuditLogReader;
use Src\Platform\Application\Audit\AuditLogRepository;
use Src\Platform\Application\Audit\AuditMemberActivity;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Platform\Application\Email\EmailBodyRenderer;
use Src\Platform\Application\Email\EmailTemplateOverrideRepository;
use Src\Platform\Application\Email\PlatformAdHocEmails;
use Src\Platform\Application\Email\PlatformEmailTemplates;
use Src\Platform\Application\EmailSuppression\EmailEventParsers;
use Src\Platform\Application\EmailSuppression\EmailSuppressionRepository;
use Src\Platform\Application\PlatformPermissions;
use Src\Platform\Application\PlatformSettings;
use Src\Platform\Application\Portal\EditPortalBrand;
use Src\Platform\Application\Portal\PortalAlerts;
use Src\Platform\Application\Portal\PortalEmailTemplates;
use Src\Platform\Application\Portal\PortalGroupFeature;
use Src\Platform\Application\Portal\PortalPreviewTokens;
use Src\Platform\Application\Portal\Portals;
use Src\Platform\Application\Portal\PortalSingleCompany;
use Src\Platform\Application\Portal\PortalVerificationFeature;
use Src\Platform\Application\Scheduler\ScheduledTaskRunRepository;
use Src\Platform\Application\Scheduler\ScheduledTasks;
use Src\Platform\Application\Scheduler\SchedulerAlerts;
use Src\Platform\Application\Scheduler\SchedulerEmailTemplates;
use Src\Platform\Application\Settings\EditCompanySettings;
use Src\Platform\Application\Settings\EditGroupSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Platform\Application\Settings\StoredSettings;
use Src\Platform\Infrastructure\Dashboard\OperationsCard;
use Src\Platform\Infrastructure\Email\CommonMarkEmailBodyRenderer;
use Src\Platform\Infrastructure\Listeners\DropSuppressedRecipients;
use Src\Platform\Infrastructure\Listeners\RecordScheduledTaskEvents;
use Src\Platform\Infrastructure\Mail\MailPortalAlerts;
use Src\Platform\Infrastructure\Mail\MailSchedulerAlerts;
use Src\Platform\Infrastructure\Persistence\DatabaseAuditLogReader;
use Src\Platform\Infrastructure\Persistence\DatabaseAuditLogRepository;
use Src\Platform\Infrastructure\Persistence\DatabaseEmailSuppressionRepository;
use Src\Platform\Infrastructure\Persistence\DatabaseEmailTemplateOverrideRepository;
use Src\Platform\Infrastructure\Persistence\DatabasePortals;
use Src\Platform\Infrastructure\Persistence\DatabaseScheduledTaskRunRepository;
use Src\Platform\Infrastructure\Persistence\DatabaseSettingsStore;
use Src\Platform\Infrastructure\Portal\EncryptedPortalPreviewTokens;
use Src\Platform\Infrastructure\Scheduler\LaravelScheduledTasks;
use Src\Platform\Interface\Console\CheckPortalCommand;
use Src\Platform\Interface\Console\CheckScheduledTasksCommand;
use Src\Platform\Interface\Console\PurgeAuditLogsCommand;
use Src\Platform\Interface\Console\RecordSchedulerTickCommand;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Audit\MemberActivity;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\AdHocEmails;
use Src\Shared\Application\Email\EmailPreviewer;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Portal\GroupFeature;
use Src\Shared\Application\Portal\PortalBrandEditor;
use Src\Shared\Application\Portal\SingleCompany;
use Src\Shared\Application\Portal\VerificationFeature;
use Src\Shared\Application\Settings\CompanySettingsEditor;
use Src\Shared\Application\Settings\GroupSettingsEditor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Src\Shared\Infrastructure\Email\ViewEmailPreviewer;

final class PlatformServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        AuditTrail::class => RecordAuditEntry::class,
        AuditLogRepository::class => DatabaseAuditLogRepository::class,
        AuditLogReader::class => DatabaseAuditLogReader::class,
        MemberActivity::class => AuditMemberActivity::class,
        Settings::class => StoredSettings::class,
        CompanySettingsEditor::class => EditCompanySettings::class,
        GroupSettingsEditor::class => EditGroupSettings::class,
        EmailTemplateOverrideRepository::class => DatabaseEmailTemplateOverrideRepository::class,
        EmailBodyRenderer::class => CommonMarkEmailBodyRenderer::class,
        EmailTemplates::class => PlatformEmailTemplates::class,
        EmailPreviewer::class => ViewEmailPreviewer::class,
        AdHocEmails::class => PlatformAdHocEmails::class,
        EmailSuppressionRepository::class => DatabaseEmailSuppressionRepository::class,
        EmailSuppressions::class => DatabaseEmailSuppressionRepository::class,
        ScheduledTaskRunRepository::class => DatabaseScheduledTaskRunRepository::class,
        ScheduledTasks::class => LaravelScheduledTasks::class,
        SchedulerAlerts::class => MailSchedulerAlerts::class,
        PortalAlerts::class => MailPortalAlerts::class,
        Portals::class => DatabasePortals::class,
        PortalPreviewTokens::class => EncryptedPortalPreviewTokens::class,
        PortalBrandEditor::class => EditPortalBrand::class,
        VerificationFeature::class => PortalVerificationFeature::class,
        GroupFeature::class => PortalGroupFeature::class,
        SingleCompany::class => PortalSingleCompany::class,
    ];

    /** One store per request, so the in-memory copy of the values is shared and dropped on every write. @var array<class-string, class-string> */
    public array $singletons = [
        SettingsStore::class => DatabaseSettingsStore::class,
        EmailEventParsers::class => EmailEventParsers::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(OperationsCard::class),
            );
        });

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...PlatformPermissions::definitions());
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...PlatformSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...SchedulerEmailTemplates::definitions(), ...PortalEmailTemplates::definitions());
        });

        $this->commands([PurgeAuditLogsCommand::class, CheckScheduledTasksCommand::class, RecordSchedulerTickCommand::class, CheckPortalCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeAuditLogsCommand::class)->dailyAt('03:00')->withoutOverlapping()->onOneServer();
            $schedule->command(CheckPortalCommand::class)->dailyAt('03:30')->withoutOverlapping()->onOneServer();
            $schedule->command(RecordSchedulerTickCommand::class)->everyMinute();
            $schedule->command(CheckScheduledTasksCommand::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, DropSuppressedRecipients::class);
        Event::listen(ScheduledTaskStarting::class, [RecordScheduledTaskEvents::class, 'onStarting']);
        Event::listen(ScheduledTaskFinished::class, [RecordScheduledTaskEvents::class, 'onFinished']);
        Event::listen(ScheduledTaskFailed::class, [RecordScheduledTaskEvents::class, 'onFailed']);

        parent::boot();
    }
}
