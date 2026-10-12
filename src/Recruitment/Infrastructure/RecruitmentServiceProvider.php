<?php

namespace Src\Recruitment\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Recruitment\Application\ApplicantApplicationReader;
use Src\Recruitment\Application\ApplicationNotificationType;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\EraseScorecardData;
use Src\Recruitment\Application\InterviewEmailSender;
use Src\Recruitment\Application\InterviewResults;
use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\OfferEmailSender;
use Src\Recruitment\Application\ProvisionDefaultStages;
use Src\Recruitment\Application\RecruiterApplicationReader;
use Src\Recruitment\Application\RecruitmentEmailTemplates;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\ScreeningQuestionReader;
use Src\Recruitment\Application\ScreeningQuestionUsage;
use Src\Recruitment\Application\StageChangeEmailSender;
use Src\Recruitment\Application\TeamApplicationRefs;
use Src\Recruitment\Domain\ApplicantDataEraser;
use Src\Recruitment\Domain\ApplicationNoteRepository;
use Src\Recruitment\Domain\ApplicationRatingRepository;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicantsExported;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewQuestionRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\JobKitDefaults;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\ScorecardEraser;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Infrastructure\Analytics\ApplicationsDailyRollup;
use Src\Recruitment\Infrastructure\Analytics\FunnelReport;
use Src\Recruitment\Infrastructure\Analytics\MatchingReport;
use Src\Recruitment\Infrastructure\Analytics\RejectionsReport;
use Src\Recruitment\Infrastructure\Analytics\TransitionsDailyRollup;
use Src\Recruitment\Infrastructure\Dashboard\ApplicantApplicationsCard;
use Src\Recruitment\Infrastructure\Dashboard\ApplicantInterviewsCard;
use Src\Recruitment\Infrastructure\Dashboard\CompanyApplicationsCard;
use Src\Recruitment\Infrastructure\Dashboard\CompanyScheduleCard;
use Src\Recruitment\Infrastructure\Dashboard\PlatformApplicationsCard;
use Src\Recruitment\Infrastructure\Listeners\EraseApplicantDataWhenUserAnonymized;
use Src\Recruitment\Infrastructure\Listeners\EraseScorecardDataWhenUserAnonymized;
use Src\Recruitment\Infrastructure\Listeners\NotifyApplicantOfStageChangeListener;
use Src\Recruitment\Infrastructure\Listeners\NotifyAssigneesOfApplication;
use Src\Recruitment\Infrastructure\Listeners\NotifyOfInterviewChangeListener;
use Src\Recruitment\Infrastructure\Listeners\NotifyOfOfferChangeListener;
use Src\Recruitment\Infrastructure\Listeners\RecordRecruitmentAuditEntries;
use Src\Recruitment\Infrastructure\Mail\MailInterviewSender;
use Src\Recruitment\Infrastructure\Mail\MailOfferSender;
use Src\Recruitment\Infrastructure\Mail\MailStageChangeSender;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantApplicationReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantDataEraser;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicantRelations;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationNoteRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationRatingRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseApplicationUsage;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewKitRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewPanel;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseInterviewResults;
use Src\Recruitment\Infrastructure\Persistence\DatabaseJobKitDefaults;
use Src\Recruitment\Infrastructure\Persistence\DatabaseJobQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseOfferRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseRecruiterApplicationReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScorecardEraser;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScorecardRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionReader;
use Src\Recruitment\Infrastructure\Persistence\DatabaseScreeningQuestionRepository;
use Src\Recruitment\Infrastructure\Persistence\DatabaseStageRepository;
use Src\Recruitment\Interface\Console\ProcessOfferDeadlinesCommand;
use Src\Recruitment\Interface\Console\ProvisionDefaultStagesCommand;
use Src\Recruitment\Interface\Console\SendInterviewRemindersCommand;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Analytics\AnalyticsReports;
use Src\Shared\Application\Analytics\AnalyticsRollups;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\Recruitment\ApplicationCounts;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\Recruitment\JobApplications;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Application\Settings\SettingsCatalog;
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
        ApplicationRepository::class => DatabaseApplicationRepository::class,
        ApplicationNoteRepository::class => DatabaseApplicationNoteRepository::class,
        ApplicationRatingRepository::class => DatabaseApplicationRatingRepository::class,
        InterviewRepository::class => DatabaseInterviewRepository::class,
        InterviewKitRepository::class => DatabaseInterviewKitRepository::class,
        JobKitDefaults::class => DatabaseJobKitDefaults::class,
        InterviewQuestionRepository::class => DatabaseInterviewQuestionRepository::class,
        InterviewPanel::class => DatabaseInterviewPanel::class,
        ScorecardRepository::class => DatabaseScorecardRepository::class,
        ScorecardEraser::class => DatabaseScorecardEraser::class,
        InterviewResults::class => DatabaseInterviewResults::class,
        InterviewEmailSender::class => MailInterviewSender::class,
        OfferRepository::class => DatabaseOfferRepository::class,
        OfferEmailSender::class => MailOfferSender::class,
        ApplicantDataEraser::class => DatabaseApplicantDataEraser::class,
        StageChangeEmailSender::class => MailStageChangeSender::class,
        ApplicantApplicationReader::class => DatabaseApplicantApplicationReader::class,
        RecruiterApplicationReader::class => DatabaseRecruiterApplicationReader::class,
        JobQuestionRepository::class => DatabaseJobQuestionRepository::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(AnalyticsReports::class, function (AnalyticsReports $reports): void {
            $reports->add(
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
                $this->app->make(PlatformApplicationsCard::class),
            );
        });

        $this->commands([ProvisionDefaultStagesCommand::class, SendInterviewRemindersCommand::class, ProcessOfferDeadlinesCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendInterviewRemindersCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(ProcessOfferDeadlinesCommand::class)->hourly()->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...RecruitmentSettings::definitions());
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
        });
    }

    public function boot(): void
    {
        Event::listen(ApplicantsExported::class, [RecordRecruitmentAuditEntries::class, 'onApplicantsExported']);

        parent::boot();

        RateLimiter::for(self::EXPORT_LIMITER, fn (Request $request): Limit => Limit::perHour(self::EXPORTS_PER_HOUR)->by('applicant-export|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Event::listen(ApplicationSubmitted::class, NotifyAssigneesOfApplication::class);
        Event::listen(ApplicationStageChanged::class, [NotifyApplicantOfStageChangeListener::class, 'inApp']);
        Event::listen(ApplicationStageChanged::class, [NotifyApplicantOfStageChangeListener::class, 'email']);
        Event::listen(InterviewChanged::class, [NotifyOfInterviewChangeListener::class, 'inApp']);
        Event::listen(InterviewChanged::class, [NotifyOfInterviewChangeListener::class, 'email']);
        Event::listen(OfferChanged::class, [NotifyOfOfferChangeListener::class, 'inApp']);
        Event::listen(OfferChanged::class, [NotifyOfOfferChangeListener::class, 'email']);
        $this->app->tag(EraseApplicantData::class, ErasureStep::class);
        Event::listen(UserAnonymized::class, EraseApplicantDataWhenUserAnonymized::class);
        $this->app->tag(EraseScorecardData::class, ErasureStep::class);
        Event::listen(UserAnonymized::class, EraseScorecardDataWhenUserAnonymized::class);
    }
}
