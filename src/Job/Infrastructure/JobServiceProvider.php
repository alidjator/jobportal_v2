<?php

namespace Src\Job\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Job\Application\Admin\AdminJobReader;
use Src\Job\Application\Browse\AlertJobMatcher;
use Src\Job\Application\Browse\JobSearchReader;
use Src\Job\Application\Browse\SearchThrottle;
use Src\Job\Application\JobPermissions;
use Src\Job\Application\JobReader;
use Src\Job\Application\JobSettings;
use Src\Job\Application\JobSlugGenerator;
use Src\Job\Application\Matching\JobSignalProvider;
use Src\Job\Application\Matching\JobSignalReader;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Job\Infrastructure\Listeners\NotifyAssignees;
use Src\Job\Infrastructure\Listeners\NotifyAssigneesOfDecision;
use Src\Job\Infrastructure\Listeners\NotifyAssigneesOfExpiry;
use Src\Job\Infrastructure\Listeners\ReassignJobsWhenMemberRemoved;
use Src\Job\Infrastructure\Persistence\DatabaseAdminJobReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobAssignmentRepository;
use Src\Job\Infrastructure\Persistence\DatabaseJobReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobRepository;
use Src\Job\Infrastructure\Persistence\DatabaseJobSearchReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobSignalReader;
use Src\Job\Interface\Console\ExpireDueJobsCommand;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\Matching\JobSignalSource;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobServiceProvider extends ContextServiceProvider
{
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
        JobMatcher::class => AlertJobMatcher::class,
        JobSignalReader::class => DatabaseJobSignalReader::class,
        JobSignalSource::class => JobSignalProvider::class,
    ];

    public function register(): void
    {
        $this->commands([ExpireDueJobsCommand::class]);

        $this->callAfterResolving(PermissionCatalog::class, function (PermissionCatalog $catalog): void {
            $catalog->add(...JobPermissions::definitions());
        });

        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...JobSettings::definitions());
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ExpireDueJobsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
        });
    }

    public function boot(): void
    {
        Event::listen(JobAssigneesChanged::class, NotifyAssignees::class);
        Event::listen(JobStatusChanged::class, NotifyAssigneesOfExpiry::class);
        Event::listen(JobDecided::class, NotifyAssigneesOfDecision::class);
        Event::listen(CompanyMemberRemoved::class, ReassignJobsWhenMemberRemoved::class);

        parent::boot();
    }
}
