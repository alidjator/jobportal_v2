<?php

namespace Src\JobSeeker\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\JobSeeker\Application\ApplicantProfileReader;
use Src\JobSeeker\Application\Candidates\CandidateReader;
use Src\JobSeeker\Application\Candidates\JobSeekerCandidateDirectory;
use Src\JobSeeker\Application\Cv\AccountPictures;
use Src\JobSeeker\Application\Cv\CvRenderer;
use Src\JobSeeker\Application\EraseJobSeekerData;
use Src\JobSeeker\Application\Export\DataExportEmailSender;
use Src\JobSeeker\Application\Export\DataExportEmailTemplates;
use Src\JobSeeker\Application\Export\ProfileDataExporter;
use Src\JobSeeker\Application\ImportSummary\ImportSummaryDataExporter;
use Src\JobSeeker\Application\JobSeekerPermissions;
use Src\JobSeeker\Application\JobSeekerSettings;
use Src\JobSeeker\Application\Reminders\ProfileReminderEmailSender;
use Src\JobSeeker\Application\Reminders\ProfileReminderEmailTemplates;
use Src\JobSeeker\Application\Reminders\ProfileReminderNotificationType;
use Src\JobSeeker\Domain\CvGenerationRepository;
use Src\JobSeeker\Domain\CvTemplateRepository;
use Src\JobSeeker\Domain\DataExportRepository;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\Events\DataExportRequested;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\ImportSummaryRepository;
use Src\JobSeeker\Domain\JobSeekerDataEraser;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\JobSeeker\Domain\ProfileReminderRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ProfileViewRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\JobSeeker\Infrastructure\Dashboard\ProfileCard;
use Src\JobSeeker\Infrastructure\Listeners\BuildDataExportWhenRequested;
use Src\JobSeeker\Infrastructure\Listeners\EraseJobSeekerDataWhenUserAnonymized;
use Src\JobSeeker\Infrastructure\Mail\MailDataExportSender;
use Src\JobSeeker\Infrastructure\Mail\MailProfileReminderSender;
use Src\JobSeeker\Infrastructure\Pdf\DompdfCvRenderer;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseAccountPictures;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseCandidateReader;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseCandidateSignalSource;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseCvGenerationRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseCvTemplateRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseDataExportRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseEducationRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseExperienceRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseImportSummaryRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseJobSeekerDataEraser;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseProfileListRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseProfileReminderRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseProfileRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseProfileViewRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseResumeRepository;
use Src\JobSeeker\Interface\Console\CleanUpDataExportsCommand;
use Src\JobSeeker\Interface\Console\PurgeImportSummariesCommand;
use Src\JobSeeker\Interface\Console\SendProfileRemindersCommand;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Applicant\ApplicantProfileSource;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Application\Matching\CandidateSignalSource;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobSeekerServiceProvider extends ContextServiceProvider
{
    /** The name of the route limiter of the candidate search: 60 requests per member per minute. */
    public const string CANDIDATE_SEARCH_LIMITER = 'candidate-search';

    private const int CANDIDATE_REQUESTS_PER_MINUTE = 60;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProfileRepository::class => DatabaseProfileRepository::class,
        ExperienceRepository::class => DatabaseExperienceRepository::class,
        EducationRepository::class => DatabaseEducationRepository::class,
        ProfileListRepository::class => DatabaseProfileListRepository::class,
        ResumeRepository::class => DatabaseResumeRepository::class,
        ProfileViewRepository::class => DatabaseProfileViewRepository::class,
        CandidateReader::class => DatabaseCandidateReader::class,
        CandidateDirectory::class => JobSeekerCandidateDirectory::class,
        CandidateSignalSource::class => DatabaseCandidateSignalSource::class,
        JobSeekerDataEraser::class => DatabaseJobSeekerDataEraser::class,
        ApplicantProfileSource::class => ApplicantProfileReader::class,
        CvTemplateRepository::class => DatabaseCvTemplateRepository::class,
        CvGenerationRepository::class => DatabaseCvGenerationRepository::class,
        CvRenderer::class => DompdfCvRenderer::class,
        AccountPictures::class => DatabaseAccountPictures::class,
        ProfileReminderRepository::class => DatabaseProfileReminderRepository::class,
        ProfileReminderEmailSender::class => MailProfileReminderSender::class,
        DataExportRepository::class => DatabaseDataExportRepository::class,
        ImportSummaryRepository::class => DatabaseImportSummaryRepository::class,
        DataExportEmailSender::class => MailDataExportSender::class,
    ];

    public function register(): void
    {
        $this->commands([SendProfileRemindersCommand::class, CleanUpDataExportsCommand::class, PurgeImportSummariesCommand::class]);

        // Once a day at 10:00 Jakarta time; which accounts are due is decided by the use case.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendProfileRemindersCommand::class)->dailyAt('10:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });

        // Import summaries are readable for a retention period after the first sign-in (`identity.import_summary_retention_days`); a nightly purge deletes the older ones.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PurgeImportSummariesCommand::class)->dailyAt('03:30')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });

        // The personal data archives live 24 hours; a cleanup every hour keeps the wait for deletion short.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(CleanUpDataExportsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
        });

        $this->callAfterResolving(EmailTemplateCatalog::class, function (EmailTemplateCatalog $catalog): void {
            $catalog->add(...ProfileReminderEmailTemplates::definitions(), ...DataExportEmailTemplates::definitions());
        });

        // Email only: the dashboard already shows what is missing.
        $this->callAfterResolving(NotificationPreferenceCatalog::class, function (NotificationPreferenceCatalog $catalog): void {
            $catalog->add(new NotificationPreferenceDefinition(
                ProfileReminderNotificationType::EMAIL_PREFERENCE,
                'Email reminders to complete your profile so that you can apply for jobs.',
                [NotificationChannel::Email->value => true],
                scheduled: true,
            ));
        });

        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(ProfileCard::class),
            );
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...JobSeekerSettings::definitions());
        });

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...JobSeekerPermissions::definitions());
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::CANDIDATE_SEARCH_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::CANDIDATE_REQUESTS_PER_MINUTE)->by('candidate-search|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        $this->app->tag(EraseJobSeekerData::class, ErasureStep::class);
        $this->app->tag(ProfileDataExporter::class, PersonalDataExporter::class);
        $this->app->tag(ImportSummaryDataExporter::class, PersonalDataExporter::class);

        Event::listen(UserAnonymized::class, EraseJobSeekerDataWhenUserAnonymized::class);
        Event::listen(DataExportRequested::class, BuildDataExportWhenRequested::class);

        parent::boot();
    }
}
