<?php

namespace Src\Recruitment\Infrastructure\JobManagement;

use Illuminate\Support\ServiceProvider;
use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\JobApplicationRepository;
use Src\Recruitment\Application\JobManagement\Ports\JobFormOptions;
use Src\Recruitment\Application\JobManagement\Ports\JobIndexer;
use Src\Recruitment\Application\JobManagement\Ports\JobNotifier;
use Src\Recruitment\Application\JobManagement\Ports\LocationDirectory;
use Src\Recruitment\Application\JobManagement\Ports\PostingSettings;
use Src\Recruitment\Application\JobManagement\Ports\Transactions;
use Src\Recruitment\Domain\JobManagement\CompanyPlanRepository;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Recruitment\Infrastructure\JobManagement\Notifications\DatabaseJobNotifier;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\DbCompanyDirectory;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\DbCompanyPlanRepository;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\DbJobApplicationRepository;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\DbJobFormOptions;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\DbLocationDirectory;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\DbPostingSettings;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\DbTransactions;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\EloquentJobRepository;
use Src\Recruitment\Infrastructure\JobManagement\Persistence\NullJobIndexer;

class JobManagementServiceProvider extends ServiceProvider
{
    public array $bindings = [
        JobRepository::class => EloquentJobRepository::class,
        CompanyPlanRepository::class => DbCompanyPlanRepository::class,
        CompanyDirectory::class => DbCompanyDirectory::class,
        PostingSettings::class => DbPostingSettings::class,
        LocationDirectory::class => DbLocationDirectory::class,
        Transactions::class => DbTransactions::class,
        JobFormOptions::class => DbJobFormOptions::class,
        JobApplicationRepository::class => DbJobApplicationRepository::class,
        JobNotifier::class => DatabaseJobNotifier::class,
        JobIndexer::class => NullJobIndexer::class,
    ];
}
