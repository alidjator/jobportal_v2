<?php

namespace Src\Assessment\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Application\AssessmentPermissions;
use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Application\Attempts\AttemptSessionToken;
use Src\Assessment\Application\Attempts\NotifyOfAttemptSubmission;
use Src\Assessment\Application\Attempts\TeamSubmissionSender;
use Src\Assessment\Application\Delivery\AssessmentInvitationService;
use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Application\Delivery\TestEmailSender;
use Src\Assessment\Application\EraseAssessmentData;
use Src\Assessment\Application\Export\AssessmentDataExporter;
use Src\Assessment\Application\Invitations\InvitationLinkBuilder;
use Src\Assessment\Application\Invitations\InvitationSender;
use Src\Assessment\Application\Invitations\InvitationThrottle;
use Src\Assessment\Application\Notices\ProviderNoticeSender;
use Src\Assessment\Application\Reports\ReportPdfRenderer;
use Src\Assessment\Application\Reports\ReportVerificationLinks;
use Src\Assessment\Application\Summaries\AssessmentSummaries;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\AssessmentDataEraser;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\CompanyBatteryRepository;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Events\InvitationDeclined;
use Src\Assessment\Domain\Events\LicenceDecided;
use Src\Assessment\Domain\Events\ProviderAutoIssueChanged;
use Src\Assessment\Domain\Events\ProviderRegistered;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Assessment\Domain\Events\PsychologistInvited;
use Src\Assessment\Domain\Events\PsychologistJoined;
use Src\Assessment\Domain\Events\ReportRegisterExported;
use Src\Assessment\Domain\Events\ReportRevised;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Assessment\Domain\Events\ResultFinalized;
use Src\Assessment\Domain\ExaminationArchiveRepository;
use Src\Assessment\Domain\ExampleReportRepository;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\JobStageTestRepository;
use Src\Assessment\Domain\LicenceSubmissionRepository;
use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\ProviderNoticeRepository;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\RecommendationRuleRepository;
use Src\Assessment\Domain\ReportMessageRepository;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\SubmittedAssessmentReader;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Assessment\Infrastructure\Calendar\ApplicantAssessmentAgenda;
use Src\Assessment\Infrastructure\Calendar\AssessmentDueCalendarSource;
use Src\Assessment\Infrastructure\Listeners\AdvanceApplicationListener;
use Src\Assessment\Infrastructure\Listeners\CopyDefaultTestsWhenJobCloned;
use Src\Assessment\Infrastructure\Listeners\EraseAssessmentDataWhenUserAnonymized;
use Src\Assessment\Infrastructure\Listeners\NotifyOfAttemptSubmissionListener;
use Src\Assessment\Infrastructure\Listeners\NotifyOfInvitationChangeListener;
use Src\Assessment\Infrastructure\Listeners\NotifyOfInvitationDeclinedListener;
use Src\Assessment\Infrastructure\Listeners\NotifyOfLicenceDecisionListener;
use Src\Assessment\Infrastructure\Listeners\NotifyOfReportSignedListener;
use Src\Assessment\Infrastructure\Listeners\RecordAssessmentAuditEntries;
use Src\Assessment\Infrastructure\Listeners\StoreReportPdfListener;
use Src\Assessment\Infrastructure\Mail\MailInvitationSender;
use Src\Assessment\Infrastructure\Mail\MailProviderNoticeSender;
use Src\Assessment\Infrastructure\Mail\MailTeamSubmissionSender;
use Src\Assessment\Infrastructure\Mail\MailTestSender;
use Src\Assessment\Infrastructure\Pdf\DompdfReportRenderer;
use Src\Assessment\Infrastructure\Persistence\DatabaseAnswerRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseAssessmentDataEraser;
use Src\Assessment\Infrastructure\Persistence\DatabaseAttemptOutcomeRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseAttemptRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseCompanyBatteryRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseExaminationArchiveRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseExampleReportRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseInvitationRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseJobStageTestRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseLicenceSubmissionRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseNormRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseProviderNoticeRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseProviderRepository;
use Src\Assessment\Infrastructure\Persistence\DatabasePsychologistInvitationRepository;
use Src\Assessment\Infrastructure\Persistence\DatabasePsychologistRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseQuestionImageRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseQuestionRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseRecommendationRuleRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseReportMessageRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseReportRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseReportTemplateRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseSectionRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseSubmittedAssessmentReader;
use Src\Assessment\Infrastructure\Persistence\DatabaseTestRepository;
use Src\Assessment\Infrastructure\Persistence\DatabaseVideoTakeRepository;
use Src\Assessment\Infrastructure\Tasks\ApplicantAssessmentTasks;
use Src\Assessment\Infrastructure\Tasks\MyScoringTask;
use Src\Assessment\Interface\Console\AutoSubmitAttemptsCommand;
use Src\Assessment\Interface\Console\CreatePsychologistCommand;
use Src\Assessment\Interface\Console\ExpireInvitationsCommand;
use Src\Assessment\Interface\Console\NotifyOfLicenceExpiryCommand;
use Src\Assessment\Interface\Console\NotifyOfReviewsWaitingCommand;
use Src\Assessment\Interface\Console\PurgeArchivedExaminationsCommand;
use Src\Assessment\Interface\Console\PurgeExpiredVideosCommand;
use Src\Assessment\Interface\Console\PurgeOrphanQuestionImagesCommand;
use Src\Assessment\Interface\Console\PurgeStaleVideoChunksCommand;
use Src\Assessment\Interface\Console\SendInvitationRemindersCommand;
use Src\Assessment\Interface\Console\ShowArchivedExaminationCommand;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Job\Domain\Events\JobCloned;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Assessment\ApplicationAssessmentSummaries;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Calendar\ApplicantAgendaSources;
use Src\Shared\Application\Calendar\CalendarSources;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Tasks\ApplicantTaskSources;
use Src\Shared\Application\Tasks\TaskProviders;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class AssessmentServiceProvider extends ContextServiceProvider
{
    public const string REGISTER_EXPORT_LIMITER = 'assessment-register-export';

    private const int REGISTER_EXPORTS_PER_HOUR = 10;

    public const string REPORT_MESSAGE_LIMITER = 'assessment-report-messages';

    private const int REPORT_MESSAGES_PER_HOUR = 20;

    public const string VERIFY_LIMITER = 'assessment-report-verify';

    private const int VERIFICATIONS_PER_HOUR = 30;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProviderRepository::class => DatabaseProviderRepository::class,
        PsychologistRepository::class => DatabasePsychologistRepository::class,
        LicenceSubmissionRepository::class => DatabaseLicenceSubmissionRepository::class,
        PsychologistInvitationRepository::class => DatabasePsychologistInvitationRepository::class,
        TestRepository::class => DatabaseTestRepository::class,
        SectionRepository::class => DatabaseSectionRepository::class,
        QuestionRepository::class => DatabaseQuestionRepository::class,
        QuestionImageRepository::class => DatabaseQuestionImageRepository::class,
        NormRepository::class => DatabaseNormRepository::class,
        RecommendationRuleRepository::class => DatabaseRecommendationRuleRepository::class,
        ReportTemplateRepository::class => DatabaseReportTemplateRepository::class,
        ExampleReportRepository::class => DatabaseExampleReportRepository::class,
        CompanyBatteryRepository::class => DatabaseCompanyBatteryRepository::class,
        InvitationLinkBuilder::class => ConfiguredInvitationLinkBuilder::class,
        ReportVerificationLinks::class => ConfiguredReportVerificationLinks::class,
        ProviderNoticeRepository::class => DatabaseProviderNoticeRepository::class,
        ProviderNoticeSender::class => MailProviderNoticeSender::class,
        InvitationThrottle::class => RateLimiterInvitationThrottle::class,
        InvitationSender::class => MailInvitationSender::class,
        InvitationRepository::class => DatabaseInvitationRepository::class,
        SubmittedAssessmentReader::class => DatabaseSubmittedAssessmentReader::class,
        JobStageTestRepository::class => DatabaseJobStageTestRepository::class,
        AssessmentInvitations::class => AssessmentInvitationService::class,
        ApplicationAssessmentSummaries::class => AssessmentSummaries::class,
        TestEmailSender::class => MailTestSender::class,
        TeamSubmissionSender::class => MailTeamSubmissionSender::class,
        AttemptRepository::class => DatabaseAttemptRepository::class,
        AnswerRepository::class => DatabaseAnswerRepository::class,
        VideoTakeRepository::class => DatabaseVideoTakeRepository::class,
        AttemptOutcomeRepository::class => DatabaseAttemptOutcomeRepository::class,
        ExaminationArchiveRepository::class => DatabaseExaminationArchiveRepository::class,
        ReportMessageRepository::class => DatabaseReportMessageRepository::class,
        ReportRepository::class => DatabaseReportRepository::class,
        ReportPdfRenderer::class => DompdfReportRenderer::class,
        AssessmentDataEraser::class => DatabaseAssessmentDataEraser::class,
    ];

    public function register(): void
    {
        $this->app->scoped(AttemptSessionToken::class);

        $this->callAfterResolving(TaskProviders::class, function (TaskProviders $tasks): void {
            $tasks->add($this->app->make(MyScoringTask::class));
        });

        $this->callAfterResolving(CalendarSources::class, function (CalendarSources $sources): void {
            $sources->add($this->app->make(AssessmentDueCalendarSource::class));
        });

        $this->callAfterResolving(ApplicantTaskSources::class, function (ApplicantTaskSources $sources): void {
            $sources->add($this->app->make(ApplicantAssessmentTasks::class));
        });

        $this->callAfterResolving(ApplicantAgendaSources::class, function (ApplicantAgendaSources $sources): void {
            $sources->add($this->app->make(ApplicantAssessmentAgenda::class));
        });

        $this->commands([CreatePsychologistCommand::class, ExpireInvitationsCommand::class, SendInvitationRemindersCommand::class, AutoSubmitAttemptsCommand::class, PurgeExpiredVideosCommand::class, PurgeStaleVideoChunksCommand::class, NotifyOfReviewsWaitingCommand::class, NotifyOfLicenceExpiryCommand::class, PurgeArchivedExaminationsCommand::class, ShowArchivedExaminationCommand::class, PurgeOrphanQuestionImagesCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendInvitationRemindersCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(ExpireInvitationsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(AutoSubmitAttemptsCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command(PurgeStaleVideoChunksCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(PurgeExpiredVideosCommand::class)->dailyAt('03:45')->withoutOverlapping()->onOneServer();
            // `phase-8-provider-institutions.md` section 7: the archived raw data whose retention ended, by date whatever the switch says.
            $schedule->command(PurgeArchivedExaminationsCommand::class)->dailyAt('03:55')->withoutOverlapping()->onOneServer();
            $schedule->command(PurgeOrphanQuestionImagesCommand::class)->dailyAt('04:05')->withoutOverlapping()->onOneServer();
            // `phase-8-provider-tools.md` section 5: the reviews waiting every hour, the licences that end once a day.
            $schedule->command(NotifyOfReviewsWaitingCommand::class)->hourlyAt(10)->withoutOverlapping()->onOneServer();
            $schedule->command(NotifyOfLicenceExpiryCommand::class)->dailyAt('06:30')->withoutOverlapping()->onOneServer();
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

        // Email only: the in-app notice is always written. Off until the assessor switches it on.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                AssessmentNotificationType::REVIEWS_PREFERENCE,
                'Email when reviews wait for a psychologist at your provider.',
                [NotificationChannel::Email->value => true],
                [ActorKind::Psychologist->value],
                scheduled: true,
            ));
            $catalog->add(new NotificationPreferenceDefinition(
                NotifyOfAttemptSubmission::PREFERENCE,
                'Email when a test you score as an assessor was submitted.',
                [NotificationChannel::Email->value => false],
                [ActorKind::CompanyMember->value],
            ));
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::REPORT_MESSAGE_LIMITER, fn (Request $request): Limit => Limit::perHour(self::REPORT_MESSAGES_PER_HOUR)->by('assessment-report-messages|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for(self::VERIFY_LIMITER, fn (Request $request): Limit => Limit::perHour(self::VERIFICATIONS_PER_HOUR)->by('assessment-report-verify|'.$request->ip()));
        RateLimiter::for(self::REGISTER_EXPORT_LIMITER, fn (Request $request): Limit => Limit::perHour(self::REGISTER_EXPORTS_PER_HOUR)->by('assessment-register-export|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        foreach ([
            ProviderRegistered::class => 'onProviderRegistered',
            PsychologistInvited::class => 'onPsychologistInvited',
            PsychologistJoined::class => 'onPsychologistJoined',
            PsychologistChanged::class => 'onPsychologistChanged',
            LicenceDecided::class => 'onLicenceDecided',
            ReportSigned::class => 'onReportSigned',
            ReportRevised::class => 'onReportRevised',
            ReportRegisterExported::class => 'onReportRegisterExported',
            ProviderAutoIssueChanged::class => 'onProviderAutoIssueChanged',
        ] as $event => $method) {
            Event::listen($event, [RecordAssessmentAuditEntries::class, $method]);
        }

        Event::listen(InvitationChanged::class, [NotifyOfInvitationChangeListener::class, 'inApp']);
        Event::listen(InvitationChanged::class, [NotifyOfInvitationChangeListener::class, 'email']);
        Event::listen(AttemptSubmitted::class, [NotifyOfAttemptSubmissionListener::class, 'inApp']);
        Event::listen(AttemptSubmitted::class, [NotifyOfAttemptSubmissionListener::class, 'email']);
        Event::listen(LicenceDecided::class, NotifyOfLicenceDecisionListener::class);
        Event::listen(ResultFinalized::class, AdvanceApplicationListener::class);
        Event::listen(JobCloned::class, CopyDefaultTestsWhenJobCloned::class);
        Event::listen(InvitationDeclined::class, NotifyOfInvitationDeclinedListener::class);
        Event::listen(ReportSigned::class, [NotifyOfReportSignedListener::class, 'inApp']);
        Event::listen(ReportSigned::class, [NotifyOfReportSignedListener::class, 'candidateInApp']);
        Event::listen(ReportSigned::class, [NotifyOfReportSignedListener::class, 'candidateEmail']);
        Event::listen(ReportRevised::class, [NotifyOfReportSignedListener::class, 'revisedInApp']);
        Event::listen(ReportRevised::class, [NotifyOfReportSignedListener::class, 'revisedCandidateInApp']);
        Event::listen(ReportRevised::class, [NotifyOfReportSignedListener::class, 'revisedCandidateEmail']);
        Event::listen(ReportSigned::class, [StoreReportPdfListener::class, 'signed']);
        Event::listen(ReportRevised::class, [StoreReportPdfListener::class, 'revised']);

        $this->app->tag(EraseAssessmentData::class, ErasureStep::class);
        $this->app->tag(AssessmentDataExporter::class, PersonalDataExporter::class);
        Event::listen(UserAnonymized::class, EraseAssessmentDataWhenUserAnonymized::class);

        parent::boot();
    }
}
