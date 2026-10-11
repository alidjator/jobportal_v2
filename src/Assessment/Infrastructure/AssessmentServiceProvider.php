<?php

namespace Src\Assessment\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Application\AssessmentPermissions;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Application\Delivery\AssessmentInvitationService;
use Src\Assessment\Application\Delivery\TestEmailSender;
use Src\Assessment\Application\Invitations\InvitationLinkBuilder;
use Src\Assessment\Application\Invitations\InvitationSender;
use Src\Assessment\Application\Invitations\InvitationThrottle;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\CompanyBatteryRepository;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Events\ProviderRegistered;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Assessment\Domain\Events\PsychologistInvited;
use Src\Assessment\Domain\Events\PsychologistJoined;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Infrastructure\Listeners\NotifyOfAttemptSubmissionListener;
use Src\Assessment\Infrastructure\Listeners\NotifyOfInvitationChangeListener;
use Src\Assessment\Infrastructure\Listeners\RecordAssessmentAuditEntries;
use Src\Assessment\Infrastructure\Mail\MailInvitationSender;
use Src\Assessment\Infrastructure\Mail\MailTestSender;
use Src\Assessment\Infrastructure\Persistence\DatabaseAnswerRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseAttemptOutcomeRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseAttemptRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseCompanyBatteryRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseInvitationRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseNormRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseProviderRepository;
use Src\Assessment\Infrastructure\Persistence\DatabasePsychologistInvitationRepository;
use Src\Assessment\Infrastructure\Persistence\DatabasePsychologistRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseQuestionRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseReportTemplateRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseSectionRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseTestRepository;
use Src\Assessment\Interface\Console\AutoSubmitAttemptsCommand;
use Src\Assessment\Interface\Console\ExpireInvitationsCommand;
use Src\Assessment\Interface\Console\SendInvitationRemindersCommand;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class AssessmentServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProviderRepository::class => DatabaseProviderRepository::class,
        PsychologistRepository::class => DatabasePsychologistRepository::class,
        PsychologistInvitationRepository::class => DatabasePsychologistInvitationRepository::class,
        TestRepository::class => DatabaseTestRepository::class,
        SectionRepository::class => DatabaseSectionRepository::class,
        QuestionRepository::class => DatabaseQuestionRepository::class,
        NormRepository::class => DatabaseNormRepository::class,
        ReportTemplateRepository::class => DatabaseReportTemplateRepository::class,
        CompanyBatteryRepository::class => DatabaseCompanyBatteryRepository::class,
        InvitationLinkBuilder::class => ConfiguredInvitationLinkBuilder::class,
        InvitationThrottle::class => RateLimiterInvitationThrottle::class,
        InvitationSender::class => MailInvitationSender::class,
        InvitationRepository::class => DatabaseInvitationRepository::class,
        AssessmentInvitations::class => AssessmentInvitationService::class,
        TestEmailSender::class => MailTestSender::class,
        AttemptRepository::class => DatabaseAttemptRepository::class,
        AnswerRepository::class => DatabaseAnswerRepository::class,
        AttemptOutcomeRepository::class => DatabaseAttemptOutcomeRepository::class,
    ];

    public function register(): void
    {
        $this->commands([ExpireInvitationsCommand::class, SendInvitationRemindersCommand::class, AutoSubmitAttemptsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendInvitationRemindersCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(ExpireInvitationsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(AutoSubmitAttemptsCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...AssessmentPermissions::definitions());
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...AssessmentSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...AssessmentEmailTemplates::definitions());
        });
    }

    public function boot(): void
    {
        foreach ([
            ProviderRegistered::class => 'onProviderRegistered',
            PsychologistInvited::class => 'onPsychologistInvited',
            PsychologistJoined::class => 'onPsychologistJoined',
            PsychologistChanged::class => 'onPsychologistChanged',
        ] as $event => $method) {
            Event::listen($event, [RecordAssessmentAuditEntries::class, $method]);
        }

        Event::listen(InvitationChanged::class, [NotifyOfInvitationChangeListener::class, 'inApp']);
        Event::listen(InvitationChanged::class, [NotifyOfInvitationChangeListener::class, 'email']);
        Event::listen(AttemptSubmitted::class, NotifyOfAttemptSubmissionListener::class);

        parent::boot();
    }
}
