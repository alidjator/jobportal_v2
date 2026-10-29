<?php

namespace Src\Approval\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Src\Approval\Application\ApprovalEmailSender;
use Src\Approval\Application\ApprovalEmailTemplates;
use Src\Approval\Application\ApprovalNotificationType;
use Src\Approval\Application\ApprovalSettings;
use Src\Approval\Application\Instance\ApprovalParticipants;
use Src\Approval\Application\Instance\SubmitForApproval;
use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\DelegationRepository;
use Src\Approval\Domain\Events\ApprovalChanged as ApprovalChangedEvent;
use Src\Approval\Domain\Events\ApprovalFlowChanged;
use Src\Approval\Domain\FlowRepository;
use Src\Approval\Infrastructure\Listeners\NotifyApprovalParticipants;
use Src\Approval\Infrastructure\Listeners\RecordApprovalAuditEntries;
use Src\Approval\Infrastructure\Listeners\VacateSlotsWhenMemberRemoved;
use Src\Approval\Infrastructure\Mail\MailApprovalEmailSender;
use Src\Approval\Infrastructure\Persistence\DatabaseApprovalInstanceRepository;
use Src\Approval\Infrastructure\Persistence\DatabaseDelegationRepository;
use Src\Approval\Infrastructure\Persistence\DatabaseFlowRepository;
use Src\Approval\Infrastructure\Tasks\ApprovalsWaitingTask;
use Src\Approval\Interface\Console\SendApprovalRemindersCommand;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Approval\ApprovalParticipation;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Tasks\TaskProviders;
use Src\Shared\Infrastructure\ContextServiceProvider;

/** The Approval context: generic approval flows of groups and tenants for the subjects other contexts register (`docs/design/erp-requisitions.md` section 4). */
final class ApprovalServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        FlowRepository::class => DatabaseFlowRepository::class,
        ApprovalInstanceRepository::class => DatabaseApprovalInstanceRepository::class,
        ApprovalParticipation::class => ApprovalParticipants::class,
        DelegationRepository::class => DatabaseDelegationRepository::class,
        Approvals::class => SubmitForApproval::class,
        ApprovalEmailSender::class => MailApprovalEmailSender::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...ApprovalSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...ApprovalEmailTemplates::definitions());
        });

        // Email only: the in-app notifications are always written. Both default to on.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(
                new NotificationPreferenceDefinition(ApprovalNotificationType::TASK_PREFERENCE, 'Email when an approval is waiting for your decision, and reminders while it waits.', [NotificationChannel::Email->value => true], [ActorKind::CompanyMember->value]),
                new NotificationPreferenceDefinition(ApprovalNotificationType::UPDATE_PREFERENCE, 'Email when a request you made is approved, rejected or returned for revision, or when one of its approvers left.', [NotificationChannel::Email->value => true], [ActorKind::CompanyMember->value]),
            );
        });

        $this->callAfterResolving(TaskProviders::class, function (TaskProviders $tasks): void {
            $tasks->add($this->app->make(ApprovalsWaitingTask::class));
        });

        $this->commands([SendApprovalRemindersCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendApprovalRemindersCommand::class)->dailyAt('08:00')->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(ApprovalFlowChanged::class, [RecordApprovalAuditEntries::class, 'onFlowChanged']);
        Event::listen(ApprovalChangedEvent::class, [RecordApprovalAuditEntries::class, 'onApprovalChanged']);
        Event::listen(CompanyMemberRemoved::class, VacateSlotsWhenMemberRemoved::class);
        Event::listen(ApprovalChangedEvent::class, NotifyApprovalParticipants::class);
    }
}
