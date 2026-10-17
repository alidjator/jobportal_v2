<?php

namespace Src\Platform\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Src\Platform\Application\Audit\AuditLogReader;
use Src\Platform\Application\Audit\AuditLogRepository;
use Src\Platform\Application\Audit\AuditMemberActivity;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Platform\Application\Email\EmailBodyRenderer;
use Src\Platform\Application\Email\EmailTemplateOverrideRepository;
use Src\Platform\Application\Email\PlatformAdHocEmails;
use Src\Platform\Application\Email\PlatformEmailTemplates;
use Src\Platform\Application\PlatformPermissions;
use Src\Platform\Application\Settings\EditCompanySettings;
use Src\Platform\Application\Settings\EditGroupSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Platform\Application\Settings\StoredSettings;
use Src\Platform\Infrastructure\Dashboard\OperationsCard;
use Src\Platform\Infrastructure\Email\CommonMarkEmailBodyRenderer;
use Src\Platform\Infrastructure\Persistence\DatabaseAuditLogReader;
use Src\Platform\Infrastructure\Persistence\DatabaseAuditLogRepository;
use Src\Platform\Infrastructure\Persistence\DatabaseEmailTemplateOverrideRepository;
use Src\Platform\Infrastructure\Persistence\DatabaseSettingsStore;
use Src\Platform\Interface\Console\PurgeAuditLogsCommand;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Audit\MemberActivity;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\AdHocEmails;
use Src\Shared\Application\Email\EmailPreviewer;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\CompanySettingsEditor;
use Src\Shared\Application\Settings\GroupSettingsEditor;
use Src\Shared\Application\Settings\Settings;
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
    ];

    /** One store per request, so the in-memory copy of the values is shared and dropped on every write. @var array<class-string, class-string> */
    public array $singletons = [
        SettingsStore::class => DatabaseSettingsStore::class,
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

        $this->commands([PurgeAuditLogsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeAuditLogsCommand::class)->dailyAt('03:00')->withoutOverlapping()->onOneServer();
        });
    }
}
