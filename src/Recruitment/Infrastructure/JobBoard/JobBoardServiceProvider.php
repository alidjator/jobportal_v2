<?php

namespace Src\Recruitment\Infrastructure\JobBoard;

use Illuminate\Support\ServiceProvider;
use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\HomeCatalog;
use Src\Recruitment\Domain\JobBoard\JobActivityNotifier;
use Src\Recruitment\Domain\JobBoard\JobCatalog;
use Src\Recruitment\Domain\JobBoard\SalaryFormatter;
use Src\Recruitment\Infrastructure\JobBoard\Persistence\QueryCandidateJobActivity;
use Src\Recruitment\Infrastructure\JobBoard\Persistence\QueryHomeCatalog;
use Src\Recruitment\Infrastructure\JobBoard\Persistence\QueryJobCatalog;

class JobBoardServiceProvider extends ServiceProvider
{
    public array $bindings = [
        JobCatalog::class => QueryJobCatalog::class,
        HomeCatalog::class => QueryHomeCatalog::class,
        CandidateJobActivity::class => QueryCandidateJobActivity::class,
        JobActivityNotifier::class => DatabaseJobActivityNotifier::class,
        SalaryFormatter::class => RequestCurrencySalaryFormatter::class,
    ];
}
