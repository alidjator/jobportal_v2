<?php

namespace Src\Job\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Company\Domain\Events\CompanyNameChangeDecided;
use Src\Company\Domain\Events\CompanyRenamed;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Job\Application\Admin\AdminJobReader;
use Src\Job\Application\Browse\AlertJobMatcher;
use Src\Job\Application\Browse\JobProfileReader;
use Src\Job\Application\Browse\JobSearchReader;
use Src\Job\Application\Browse\LiveJobProfileProvider;
use Src\Job\Application\Browse\SearchThrottle;
use Src\Job\Application\Export\SavedJobsDataExporter;
use Src\Job\Application\Insight\SalaryInsightReader;
use Src\Job\Application\JobFromRequisition;
use Src\Job\Application\JobPermissions;
use Src\Job\Application\JobReader;
use Src\Job\Application\JobSettings;
use Src\Job\Application\JobSlugGenerator;
use Src\Job\Application\Matching\JobSignalProvider;
use Src\Job\Application\Matching\JobSignalReader;
use Src\Job\Application\Reports\EraseJobReports;
use Src\Job\Application\Reports\JobReportReader;
use Src\Job\Application\Saved\EraseSavedJobs;
use Src\Job\Application\Views\EraseJobViews;
use Src\Job\Application\Views\JobViewLog;
use Src\Job\Application\Views\JobViewRecorder;
use Src\Job\Application\Views\VisitorKeys;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Events\JobHeld;
use Src\Job\Domain\Events\JobReportsClosed;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobReportRepository;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\SavedJobRepository;
use Src\Job\Infrastructure\Analytics\JobMarketDailyRollup;
use Src\Job\Infrastructure\Analytics\MarketReport;
use Src\Job\Infrastructure\Dashboard\CompanyJobsCard;
use Src\Job\Infrastructure\Dashboard\JobApprovalQueueCard;
use Src\Job\Infrastructure\Dashboard\JobPerformanceCard;
use Src\Job\Infrastructure\Dashboard\PlatformJobsCard;
use Src\Job\Infrastructure\Insight\CachedSalaryInsightReader;
use Src\Job\Infrastructure\Listeners\EraseJobReportsWhenUserAnonymized;
use Src\Job\Infrastructure\Listeners\EraseJobViewsWhenUserAnonymized;
use Src\Job\Infrastructure\Listeners\EraseSavedJobsWhenUserAnonymized;
use Src\Job\Infrastructure\Listeners\NotifyAssignees;
use Src\Job\Infrastructure\Listeners\NotifyAssigneesOfDecision;
use Src\Job\Infrastructure\Listeners\NotifyAssigneesOfExpiry;
use Src\Job\Infrastructure\Listeners\NotifyAssigneesOfHold;
use Src\Job\Infrastructure\Listeners\NotifyReportersOfReview;
use Src\Job\Infrastructure\Listeners\ReassignJobsWhenMemberRemoved;
use Src\Job\Infrastructure\Listeners\RecordJobAuditEntries;
use Src\Job\Infrastructure\Listeners\RefreshSearchVectorsOfCompany;
use Src\Job\Infrastructure\Persistence\DatabaseAdminJobReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobAssignmentRepository;
use Src\Job\Infrastructure\Persistence\DatabaseJobProfileReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobReportReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobReportRepository;
use Src\Job\Infrastructure\Persistence\DatabaseJobRepository;
use Src\Job\Infrastructure\Persistence\DatabaseJobSearchReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobSignalReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobViewLog;
use Src\Job\Infrastructure\Persistence\DatabaseRequisitionJobs;
use Src\Job\Infrastructure\Persistence\DatabaseSavedJobRepository;
use Src\Job\Infrastructure\Tasks\JobsClosingTask;
use Src\Job\Infrastructure\Tasks\JobsDraftTask;
use Src\Job\Infrastructure\Tasks\JobsWithoutApplicantsTask;
use Src\Job\Interface\Console\ExpireDueJobsCommand;
use Src\Job\Interface\Console\FoldJobViewsCommand;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Analytics\AnalyticsReports;
use Src\Shared\Application\Analytics\AnalyticsRollups;
use Src\Shared\Application\Dashboard\DashboardCards;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\Job\LiveJobProfiles;
use Src\Shared\Application\Job\PublishedJobs;
use Src\Shared\Application\Job\RequisitionJobCreator;
use Src\Shared\Application\Job\RequisitionJobs;
use Src\Shared\Application\Matching\JobSignalSource;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Tasks\TaskProviders;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobServiceProvider extends ContextServiceProvider
{
    /** The name of the route limiter of the view counter: 120 requests per client address per minute. */
    public const string VIEW_LIMITER = 'job-view';

    private const int VIEWS_PER_MINUTE = 120;

    /** The name of the route limiter of the salary insight: 60 requests per client address per minute. */
    public const string INSIGHT_LIMITER = 'salary-insight';

    private const int INSIGHTS_PER_MINUTE = 60;

    /** The name of the route limiter of saving and removing a saved job: 60 requests per account per minute. */
    public const string SAVED_LIMITER = 'saved-jobs';

    private const int SAVES_PER_MINUTE = 60;

    /** The name of the route limiter of reporting a job: 10 reports per account per hour. */
    public const string REPORT_LIMITER = 'job-reports';

    private const int REPORTS_PER_HOUR = 10;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        JobRepository::class => DatabaseJobRepository::class,
        JobAssignmentRepository::class => DatabaseJobAssignmentRepository::class,
        JobReader::class => DatabaseJobReader::class,
        AdminJobReader::class => DatabaseAdminJobReader::class,
        JobSlugGenerator::class => LaravelJobSlugGenerator::class,
        JobSearchReader::class => DatabaseJobSearchReader::class,
        SearchThrottle::class => RateLimiterSearchThrottle::class,
        JobDirectory::class => RepositoryJobDirectory::class,
        RequisitionJobs::class => DatabaseRequisitionJobs::class,
        RequisitionJobCreator::class => JobFromRequisition::class,
        PublishedJobs::class => DatabasePublishedJobs::class,
        JobMatcher::class => AlertJobMatcher::class,
        LiveJobProfiles::class => LiveJobProfileProvider::class,
        JobProfileReader::class => DatabaseJobProfileReader::class,
        JobSignalReader::class => DatabaseJobSignalReader::class,
        JobSignalSource::class => JobSignalProvider::class,
        JobReportRepository::class => DatabaseJobReportRepository::class,
        JobReportReader::class => DatabaseJobReportReader::class,
        JobViewLog::class => DatabaseJobViewLog::class,
        SavedJobRepository::class => DatabaseSavedJobRepository::class,
        SalaryInsightReader::class => CachedSalaryInsightReader::class,
        JobViewRecorder::class => QueuedJobViewRecorder::class,
        VisitorKeys::class => HashedVisitorKeys::class,
    ];

    public function register(): void
    {
        $this->commands([ExpireDueJobsCommand::class, FoldJobViewsCommand::class]);

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...JobPermissions::definitions());
        });

        $this->callAfterResolving(AnalyticsReports::class, function (AnalyticsReports $reports): void {
            $reports->add($this->app->make(MarketReport::class));
        });

        $this->callAfterResolving(AnalyticsRollups::class, function (AnalyticsRollups $rollups): void {
            $rollups->add($this->app->make(JobMarketDailyRollup::class));
        });

        $this->callAfterResolving(DashboardCards::class, function (DashboardCards $cards): void {
            $cards->add(
                $this->app->make(CompanyJobsCard::class),
                $this->app->make(JobPerformanceCard::class),
                $this->app->make(PlatformJobsCard::class),
                $this->app->make(JobApprovalQueueCard::class),
            );
        });

        $this->callAfterResolving(TaskProviders::class, function (TaskProviders $tasks): void {
            $tasks->add(
                $this->app->make(JobsClosingTask::class),
                $this->app->make(JobsDraftTask::class),
                $this->app->make(JobsWithoutApplicantsTask::class),
            );
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...JobSettings::definitions());
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ExpireDueJobsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            // Once a night, Jakarta time: raw views older than 90 days are folded into the daily counts.
            $schedule->command(FoldJobViewsCommand::class)->dailyAt('03:00')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::VIEW_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::VIEWS_PER_MINUTE)->by('job-view|'.$request->ip()));

        RateLimiter::for(self::INSIGHT_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::INSIGHTS_PER_MINUTE)->by('salary-insight|'.$request->ip()));

        RateLimiter::for(self::SAVED_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::SAVES_PER_MINUTE)->by('saved-jobs|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for(self::REPORT_LIMITER, fn (Request $request): Limit => Limit::perHour(self::REPORTS_PER_HOUR)->by('job-reports|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Event::listen(JobAssigneesChanged::class, NotifyAssignees::class);
        Event::listen(JobStatusChanged::class, NotifyAssigneesOfExpiry::class);
        Event::listen(JobDecided::class, NotifyAssigneesOfDecision::class);
        Event::listen(CompanyMemberRemoved::class, ReassignJobsWhenMemberRemoved::class);
        $this->app->tag(EraseJobViews::class, ErasureStep::class);
        Event::listen(UserAnonymized::class, EraseJobViewsWhenUserAnonymized::class);
        $this->app->tag(EraseSavedJobs::class, ErasureStep::class);
        $this->app->tag(SavedJobsDataExporter::class, PersonalDataExporter::class);
        Event::listen(UserAnonymized::class, EraseSavedJobsWhenUserAnonymized::class);
        $this->app->tag(EraseJobReports::class, ErasureStep::class);
        Event::listen(UserAnonymized::class, EraseJobReportsWhenUserAnonymized::class);
        Event::listen(JobHeld::class, NotifyAssigneesOfHold::class);
        Event::listen(CompanyRenamed::class, [RefreshSearchVectorsOfCompany::class, 'onRenamed']);
        Event::listen(CompanyNameChangeDecided::class, [RefreshSearchVectorsOfCompany::class, 'onNameChangeDecided']);
        Event::listen(JobReportsClosed::class, NotifyReportersOfReview::class);

        Event::listen(JobAssigneesChanged::class, [RecordJobAuditEntries::class, 'onJobAssigneesChanged']);
        Event::listen(JobStatusChanged::class, [RecordJobAuditEntries::class, 'onJobStatusChanged']);
        Event::listen(JobDecided::class, [RecordJobAuditEntries::class, 'onJobDecided']);
        Event::listen(JobReportsClosed::class, [RecordJobAuditEntries::class, 'onJobReportsClosed']);

        parent::boot();
    }
}
