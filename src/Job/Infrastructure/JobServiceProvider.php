<?php

namespace Src\Job\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Job\Application\JobReader;
use Src\Job\Application\JobSettings;
use Src\Job\Application\JobSlugGenerator;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Job\Infrastructure\Listeners\NotifyAssignees;
use Src\Job\Infrastructure\Listeners\ReassignJobsWhenMemberRemoved;
use Src\Job\Infrastructure\Persistence\DatabaseJobAssignmentRepository;
use Src\Job\Infrastructure\Persistence\DatabaseJobReader;
use Src\Job\Infrastructure\Persistence\DatabaseJobRepository;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;

final class JobServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        JobRepository::class => DatabaseJobRepository::class,
        JobAssignmentRepository::class => DatabaseJobAssignmentRepository::class,
        JobReader::class => DatabaseJobReader::class,
        JobSlugGenerator::class => LaravelJobSlugGenerator::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...JobSettings::definitions());
        });
    }

    public function boot(): void
    {
        Event::listen(JobAssigneesChanged::class, NotifyAssignees::class);
        Event::listen(CompanyMemberRemoved::class, ReassignJobsWhenMemberRemoved::class);

        parent::boot();
    }
}
