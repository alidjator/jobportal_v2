<?php

namespace Src\Recruitment\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application as FoundationApplication;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Psr\Log\LoggerInterface;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Recruitment\Application\ApplicantApplicationReader;
use Src\Recruitment\Application\ApplicantEmailSender;
use Src\Recruitment\Application\ApplicationNotificationType;
use Src\Recruitment\Application\ApplicationPageLink;
use Src\Recruitment\Application\EmailTeamOfEvents;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\EraseScorecardData;
use Src\Recruitment\Application\Export\ApplicationsDataExporter;
use Src\Recruitment\Application\HiringManagerVisibility;
use Src\Recruitment\Application\InterviewCalendar;
use Src\Recruitment\Application\InterviewEmailSender;
use Src\Recruitment\Application\InterviewResults;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\MyInterviewReader;
use Src\Recruitment\Application\ProvisionDefaultStages;
use Src\Recruitment\Application\RecruiterApplicationReader;
use Src\Recruitment\Application\RecruitmentEmailTemplates;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\ScreeningQuestionReader;
use Src\Recruitment\Application\ScreeningQuestionUsage;
use Src\Recruitment\Application\StageChangeEmailSender;
use Src\Recruitment\Application\TeamApplicationPageLink;
use Src\Recruitment\Application\TeamApplicationRefs;
use Src\Recruitment\Application\TeamEmailSender;
use Src\Recruitment\Application\Video\IceServerProvider;
use Src\Recruitment\Application\Video\JoinTokenFactory;
use Src\Recruitment\Application\Video\LobbySignal;
use Src\Recruitment\Application\Video\RecordingConverter;
use Src\Recruitment\Application\Video\RecordingSignal;
use Src\Recruitment\Application\Video\RecordingVolume;
use Src\Recruitment\Application\Video\VideoRoomGateway;
use Src\Recruitment\Application\Voice\HandleAriEvent;
use Src\Recruitment\Application\Voice\VoiceGateway;
use Src\Recruitment\Domain\ApplicantDataEraser;
use Src\Recruitment\Domain\ApplicationNoteRepository;
use Src\Recruitment\Domain\ApplicationRatingRepository;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicantsExported;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Events\InterviewPanelAnswered;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\Events\RecordingConverted;
use Src\Recruitment\Domain\Events\RecordingStopped;
use Src\Recruitment\Domain\Events\VoiceRecordingStopped;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewQuestionRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\JobKitDefaults;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\ScorecardEraser;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\Video\AttendanceRepository;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\VideoRoomRepository;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\VoiceSessionRepository;
use Src\Recruitment\Infrastructure\Analytics\ApplicationsDailyRollup;
use Src\Recruitment\Infrastructure\Analytics\FillReport;
use Src\Recruitment\Infrastructure\Analytics\FunnelReport;
use Src\Recruitment\Infrastructure\Analytics\MatchingReport;
use Src\Recruitment\Infrastructure\Analytics\RejectionsReport;
use Src\Recruitment\Infrastructure\Analytics\TransitionsDailyRollup;
use Src\Recruitment\Infrastructure\Calendar\InterviewCalendarSource;
use Src\Recruitment\Infrastructure\Calendar\OfferCalendarSource;
use Src\Recruitment\Infrastructure\Dashboard\ApplicantApplicationsCard;
use Src\Recruitment\Infrastructure\Dashboard\ApplicantInterviewsCard;
use Src\Recruitment\Infrastructure\Dashboard\CompanyApplicationsCard;
use Src\Recruitment\Infrastructure\Dashboard\CompanyScheduleCard;
use Src\Recruitment\Infrastructure\Dashboard\CompanyTimeToFillCard;
use Src\Recruitment\Infrastructure\Dashboard\PlatformApplicationsCard;
use Src\Recruitment\Infrastructure\Listeners\ConvertRecordingWhenStopped;
use Src\Recruitment\Infrastructure\Listeners\EmailApplicantOfReceiptListener;
use Src\Recruitment\Infrastructure\Listeners\EmailTeamOfEventsListener;
use Src\Recruitment\Infrastructure\Listeners\EraseApplicantDataWhenUserAnonymized;
use Src\Recruitment\Infrastructure\Listeners\EraseScorecardDataWhenUserAnonymized;
use Src\Recruitment\Infrastructure\Listeners\NotifyApplicantOfStageChangeListener;
use Src\Recruitment\Infrastructure\Listeners\NotifyAssigneesOfApplication;
use Src\Recruitment\Infrastructure\Listeners\NotifyOfInterviewChangeListener;
use Src\Recruitment\Infrastructure\Listeners\NotifyOfOfferChangeListener;
use Src\Recruitment\Infrastructure\Listeners\NotifyOfPanelAnswerListener;
use Src\Recruitment\Infrastructure\Listeners\NotifyOfWithdrawalListener;
use Src\Recruitment\Infrastructure\Listeners\RecordRecruitmentAuditEntries;
use Src\Recruitment\Infrastructure\Listeners\StoreRecordingWhenConverted;
use Src\Recruitment\Infrastructure\Listeners\StoreVoiceRecordingWhenStopped;
use Src\Recruitment\Infrastructure\Mail\MailApplicantSender;
use Src\Recruitment\Infrastructure\Mail\MailInterviewSender;
use Src\Recruitment\Infrastructure\Mail\MailStageChangeSender;
use Src\Recruitment\Infrastructure\Mail\MailTeamSender;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantApplicationReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantDataEraser;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantRelations;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationNoteRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationRatingRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationUsage;
use Src\Recruitment\Infrastructure\Persistence\DatabaseHiringManagerVisibility;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewKitRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewPanel;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewResults;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewRsvps;
use Src\Recruitment\Infrastructure\Persistence\DatabaseJobKitDefaults;
use Src\Recruitment\Infrastructure\Persistence\DatabaseJobQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseMyInterviewReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseOfferRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseRecruiterApplicationReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScorecardEraser;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScorecardRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseStageRepository;
use Src\Recruitment\Infrastructure\Tasks\MyRsvpTask;
use Src\Recruitment\Infrastructure\Tasks\MyScorecardsTask;
use Src\Recruitment\Infrastructure\Tasks\OffersExpiringTask;
use Src\Recruitment\Infrastructure\Tasks\OffersWaitingTask;
use Src\Recruitment\Infrastructure\Tasks\PanelDeclinedTask;
use Src\Recruitment\Infrastructure\Tasks\StaleApplicationsTask;
use Src\Recruitment\Infrastructure\Video\BroadcastLobbySignal;
use Src\Recruitment\Infrastructure\Video\BroadcastRecordingSignal;
use Src\Recruitment\Infrastructure\Video\CoturnIceServers;
use Src\Recruitment\Infrastructure\Video\DatabaseAttendanceRepository;
use Src\Recruitment\Infrastructure\Video\DatabaseRecordingRepository;
use Src\Recruitment\Infrastructure\Video\DatabaseVideoRoomRepository;
use Src\Recruitment\Infrastructure\Video\HmacJoinTokenFactory;
use Src\Recruitment\Infrastructure\Video\HttpRecordingConverter;
use Src\Recruitment\Infrastructure\Video\JanusVideoRoomGateway;
use Src\Recruitment\Infrastructure\Video\LocalRecordingVolume;
use Src\Recruitment\Infrastructure\Voice\AriEventListener;
use Src\Recruitment\Infrastructure\Voice\AsteriskAriGateway;
use Src\Recruitment\Infrastructure\Voice\DatabaseCallRepository;
use Src\Recruitment\Infrastructure\Voice\DatabaseVoiceSessionRepository;
use Src\Recruitment\Interface\Broadcasting\RecruitmentChannels;
use Src\Recruitment\Interface\Console\CloseVideoRoomsCommand;
use Src\Recruitment\Interface\Console\CloseVoiceSessionsCommand;
use Src\Recruitment\Interface\Console\ListenToVoiceServerCommand;
use Src\Recruitment\Interface\Console\ProcessOfferDeadlinesCommand;
use Src\Recruitment\Interface\Console\ProvisionDefaultStagesCommand;
use Src\Recruitment\Interface\Console\PurgeExpiredRecordingsCommand;
use Src\Recruitment\Interface\Console\SendInterviewRemindersCommand;
use Src\Recruitment\Interface\Console\SendStartingSoonRemindersCommand;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Analytics\AnalyticsReports;
use Src\Shared\Application\Analytics\AnalyticsRollups;
use Src\Shared\Application\Calendar\CalendarSources;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\Recruitment\ApplicationCounts;
use Src\Shared\Application\Recruitment\ApplicationVisibility;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\Recruitment\HiringManagerBoundary;
use Src\Shared\Application\Recruitment\JobApplications;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Tasks\TaskProviders;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class RecruitmentServiceProvider extends ContextServiceProvider
{
    /** The name of the route limiter of the applicant export: 10 exports per member per hour. */
    public const string EXPORT_LIMITER = 'applicant-export';

    private const int EXPORTS_PER_HOUR = 10;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        ApplicationCounts::class => DatabaseApplicationCounts::class,
        StageRepository::class => DatabaseStageRepository::class,
        DefaultStageProvisioner::class => ProvisionDefaultStages::class,
        ScreeningQuestionRepository::class => DatabaseScreeningQuestionRepository::class,
        ScreeningQuestionReader::class => DatabaseScreeningQuestionReader::class,
        ScreeningQuestionUsage::class => DatabaseApplicationUsage::class,
        JobApplicationCheck::class => DatabaseApplicationUsage::class,
        JobApplications::class => DatabaseApplicationUsage::class,
        ApplicantRelations::class => DatabaseApplicantRelations::class,
        TeamApplications::class => TeamApplicationRefs::class,
        HiringManagerVisibility::class => DatabaseHiringManagerVisibility::class,
        HiringManagerBoundary::class => DatabaseHiringManagerVisibility::class,
        ApplicationVisibility::class => DatabaseHiringManagerVisibility::class,
        ApplicationRepository::class => DatabaseApplicationRepository::class,
        ApplicationNoteRepository::class => DatabaseApplicationNoteRepository::class,
        ApplicationRatingRepository::class => DatabaseApplicationRatingRepository::class,
        InterviewRepository::class => DatabaseInterviewRepository::class,
        InterviewKitRepository::class => DatabaseInterviewKitRepository::class,
        JobKitDefaults::class => DatabaseJobKitDefaults::class,
        InterviewQuestionRepository::class => DatabaseInterviewQuestionRepository::class,
        InterviewPanel::class => DatabaseInterviewPanel::class,
        InterviewRsvps::class => DatabaseInterviewRsvps::class,
        MyInterviewReader::class => DatabaseMyInterviewReader::class,
        ScorecardRepository::class => DatabaseScorecardRepository::class,
        ScorecardEraser::class => DatabaseScorecardEraser::class,
        InterviewResults::class => DatabaseInterviewResults::class,
        InterviewEmailSender::class => MailInterviewSender::class,
        TeamEmailSender::class => MailTeamSender::class,
        OfferRepository::class => DatabaseOfferRepository::class,
        ApplicantEmailSender::class => MailApplicantSender::class,
        ApplicantDataEraser::class => DatabaseApplicantDataEraser::class,
        StageChangeEmailSender::class => MailStageChangeSender::class,
        ApplicantApplicationReader::class => DatabaseApplicantApplicationReader::class,
        RecruiterApplicationReader::class => DatabaseRecruiterApplicationReader::class,
        JobQuestionRepository::class => DatabaseJobQuestionRepository::class,
        VideoRoomRepository::class => DatabaseVideoRoomRepository::class,
        LobbySignal::class => BroadcastLobbySignal::class,
        RecordingSignal::class => BroadcastRecordingSignal::class,
        RecordingRepository::class => DatabaseRecordingRepository::class,
        AttendanceRepository::class => DatabaseAttendanceRepository::class,
        CallRepository::class => DatabaseCallRepository::class,
        VoiceSessionRepository::class => DatabaseVoiceSessionRepository::class,
    ];

    public function register(): void
    {
        $this->app->bind(VideoRoomGateway::class, fn ($app): VideoRoomGateway => new JanusVideoRoomGateway(
            $app->make(HttpFactory::class),
            $this->service($app, 'janus.http_url'),
            $this->service($app, 'janus.admin_url'),
            $this->service($app, 'janus.admin_secret'),
            $this->service($app, 'janus.api_token'),
            $this->service($app, 'janus.ws_url'),
        ));
        $this->app->bind(VoiceGateway::class, fn ($app): VoiceGateway => new AsteriskAriGateway(
            $app->make(HttpFactory::class),
            $this->service($app, 'asterisk.ari_url'),
            $this->service($app, 'asterisk.ari_user'),
            $this->service($app, 'asterisk.ari_password'),
            $this->service($app, 'asterisk.wss_url'),
            $this->service($app, 'asterisk.sip_domain'),
            $this->service($app, 'asterisk.caller_id'),
            $this->service($app, 'asterisk.trunk_endpoint') ?? 'trunk',
        ));
        $this->app->bind(AriEventListener::class, fn ($app): AriEventListener => new AriEventListener(
            $app->make(HandleAriEvent::class),
            $app->make(LoggerInterface::class),
            $this->service($app, 'asterisk.ari_url'),
            $this->service($app, 'asterisk.ari_user'),
            $this->service($app, 'asterisk.ari_password'),
        ));
        $this->app->bind(TeamApplicationPageLink::class, fn ($app): TeamApplicationPageLink => new TeamApplicationPageLink($app->make(Repository::class)->get('app.company_application_url')));
        $this->app->bind(ApplicationPageLink::class, fn ($app): ApplicationPageLink => new ApplicationPageLink($app->make(Repository::class)->get('app.application_url')));
        $this->app->bind(InterviewCalendar::class, function ($app): InterviewCalendar {
            $config = $app->make(Repository::class);

            return new InterviewCalendar((string) $config->get('mail.from.address'), $config->get('app.application_url'));
        });
        $this->app->bind(JoinTokenFactory::class, fn ($app): JoinTokenFactory => new HmacJoinTokenFactory((string) $app->make(Repository::class)->get('app.key')));
        $this->app->bind(RecordingVolume::class, fn ($app): RecordingVolume => new LocalRecordingVolume(
            $this->service($app, 'janus.recordings_dir'),
            $this->service($app, 'janus.recordings_path'),
        ));
        $this->app->bind(RecordingConverter::class, fn ($app): RecordingConverter => new HttpRecordingConverter(
            $app->make(HttpFactory::class),
            $this->service($app, 'janus.converter_url'),
            $this->service($app, 'janus.webhook_secret'),
        ));
        $this->app->bind(IceServerProvider::class, fn ($app): IceServerProvider => new CoturnIceServers(
            array_values(array_filter(array_map('trim', explode(',', (string) $this->service($app, 'turn.urls'))))),
            $this->service($app, 'turn.secret'),
        ));

        $this->callAfterResolving(AnalyticsReports::class, function (AnalyticsReports $reports): void {
            $reports->add(
                $this->app->make(FillReport::class),
                $this->app->make(FunnelReport::class),
                $this->app->make(RejectionsReport::class),
                $this->app->make(MatchingReport::class),
            );
        });

        $this->callAfterResolving(AnalyticsRollups::class, function (AnalyticsRollups $rollups): void {
            $rollups->add($this->app->make(ApplicationsDailyRollup::class), $this->app->make(TransitionsDailyRollup::class));
        });

        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(ApplicantApplicationsCard::class),
                $this->app->make(ApplicantInterviewsCard::class),
                $this->app->make(CompanyApplicationsCard::class),
                $this->app->make(CompanyScheduleCard::class),
                $this->app->make(CompanyTimeToFillCard::class),
                $this->app->make(PlatformApplicationsCard::class),
            );
        });

        $this->callAfterResolving(TaskProviders::class, function (TaskProviders $tasks): void {
            $tasks->add(
                $this->app->make(StaleApplicationsTask::class),
                $this->app->make(MyScorecardsTask::class),
                $this->app->make(MyRsvpTask::class),
                $this->app->make(PanelDeclinedTask::class),
                $this->app->make(OffersWaitingTask::class),
                $this->app->make(OffersExpiringTask::class),
            );
        });

        $this->callAfterResolving(CalendarSources::class, function (CalendarSources $sources): void {
            $sources->add($this->app->make(InterviewCalendarSource::class), $this->app->make(OfferCalendarSource::class));
        });

        $this->commands([ProvisionDefaultStagesCommand::class, SendInterviewRemindersCommand::class, SendStartingSoonRemindersCommand::class, ProcessOfferDeadlinesCommand::class, CloseVideoRoomsCommand::class, CloseVoiceSessionsCommand::class, ListenToVoiceServerCommand::class, PurgeExpiredRecordingsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendInterviewRemindersCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(SendStartingSoonRemindersCommand::class)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command(ProcessOfferDeadlinesCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(CloseVideoRoomsCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command(CloseVoiceSessionsCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command(PurgeExpiredRecordingsCommand::class)->dailyAt('03:30')->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...RecruitmentSettings::definitions(), ...InterviewSettings::definitions());
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...RecruitmentEmailTemplates::definitions());
        });

        // Email only: push joins when push can be sent. The applicant's in-app notifications are always written.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                ApplicationNotificationType::UPDATE_PREFERENCE,
                'Email when the stage of one of your applications changes (interview, offer, hired or rejected), and when a test is sent, nearly due, expired or cancelled.',
                [NotificationChannel::Email->value => true],
            ));
            $catalog->add(
                new NotificationPreferenceDefinition(EmailTeamOfEvents::INTERVIEW_PREFERENCE, 'Email when a job seeker confirms, declines or proposes another time for an interview you take part in, and when a withdrawal cancels one.', [NotificationChannel::Email->value => true], [ActorKind::CompanyMember->value]),
                new NotificationPreferenceDefinition(EmailTeamOfEvents::SCORECARD_PREFERENCE, 'Email when your scorecard of an interview is still missing a day and three days after it.', [NotificationChannel::Email->value => true], [ActorKind::CompanyMember->value]),
                new NotificationPreferenceDefinition(EmailTeamOfEvents::OFFER_PREFERENCE, 'Email when a job seeker accepts or declines an offer you sent.', [NotificationChannel::Email->value => true], [ActorKind::CompanyMember->value]),
                new NotificationPreferenceDefinition(EmailTeamOfEvents::APPLICATION_PREFERENCE, 'Email for each new application to a job assigned to you (the daily digest counts them anyway).', [NotificationChannel::Email->value => false], [ActorKind::CompanyMember->value]),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(ApplicantsExported::class, [RecordRecruitmentAuditEntries::class, 'onApplicantsExported']);
        RecruitmentChannels::register();

        parent::boot();

        RateLimiter::for(self::EXPORT_LIMITER, fn (Request $request): Limit => Limit::perHour(self::EXPORTS_PER_HOUR)->by('applicant-export|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Event::listen(ApplicationSubmitted::class, NotifyAssigneesOfApplication::class);
        Event::listen(ApplicationSubmitted::class, EmailApplicantOfReceiptListener::class);
        Event::listen(ApplicationSubmitted::class, [EmailTeamOfEventsListener::class, 'applicationSubmitted']);
        Event::listen(InterviewChanged::class, [EmailTeamOfEventsListener::class, 'interviewChanged']);
        Event::listen(InterviewChanged::class, [EmailTeamOfEventsListener::class, 'interviewPanel']);
        Event::listen(OfferChanged::class, [EmailTeamOfEventsListener::class, 'offerChanged']);
        Event::listen(ApplicationStageChanged::class, [NotifyApplicantOfStageChangeListener::class, 'inApp']);
        Event::listen(ApplicationStageChanged::class, [NotifyApplicantOfStageChangeListener::class, 'email']);
        Event::listen(InterviewChanged::class, [NotifyOfInterviewChangeListener::class, 'inApp']);
        Event::listen(InterviewPanelAnswered::class, [NotifyOfPanelAnswerListener::class, 'inApp']);
        Event::listen(InterviewPanelAnswered::class, [NotifyOfPanelAnswerListener::class, 'email']);
        Event::listen(InterviewChanged::class, [NotifyOfInterviewChangeListener::class, 'email']);
        Event::listen(OfferChanged::class, [NotifyOfOfferChangeListener::class, 'inApp']);
        Event::listen(OfferChanged::class, [NotifyOfOfferChangeListener::class, 'email']);
        Event::listen(ApplicationWithdrawn::class, [NotifyOfWithdrawalListener::class, 'applicantInApp']);
        Event::listen(ApplicationWithdrawn::class, [NotifyOfWithdrawalListener::class, 'applicantEmail']);
        Event::listen(ApplicationWithdrawn::class, [NotifyOfWithdrawalListener::class, 'assignees']);
        Event::listen(ApplicationWithdrawn::class, [NotifyOfWithdrawalListener::class, 'interviewersInApp']);
        Event::listen(ApplicationWithdrawn::class, [NotifyOfWithdrawalListener::class, 'interviewersEmail']);
        Event::listen(ApplicationWithdrawn::class, [RecordRecruitmentAuditEntries::class, 'onApplicationWithdrawn']);
        Event::listen(RecordingStopped::class, ConvertRecordingWhenStopped::class);
        Event::listen(RecordingConverted::class, StoreRecordingWhenConverted::class);
        Event::listen(VoiceRecordingStopped::class, StoreVoiceRecordingWhenStopped::class);
        $this->app->tag(EraseApplicantData::class, ErasureStep::class);
        $this->app->tag(ApplicationsDataExporter::class, PersonalDataExporter::class);
        Event::listen(UserAnonymized::class, EraseApplicantDataWhenUserAnonymized::class);
        $this->app->tag(EraseScorecardData::class, ErasureStep::class);
        Event::listen(UserAnonymized::class, EraseScorecardDataWhenUserAnonymized::class);
    }

    /** A `services.*` value as a string, or null when it is unset or empty. */
    private function service(FoundationApplication $app, string $key): ?string
    {
        $value = $app->make(Repository::class)->get('services.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
