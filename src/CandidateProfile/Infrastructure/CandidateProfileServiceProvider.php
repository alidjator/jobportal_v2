<?php

namespace Src\CandidateProfile\Infrastructure;

use Src\CandidateProfile\Application\CandidateProfileProjection;
use Src\CandidateProfile\Domain\AccountRepository;
use Src\CandidateProfile\Domain\CandidateDirectory;
use Src\CandidateProfile\Domain\CandidateRepository;
use Src\CandidateProfile\Domain\CandidateSettingsRepository;
use Src\CandidateProfile\Domain\ResumeRepository;
use Src\CandidateProfile\Infrastructure\Persistence\QueryAccountRepository;
use Src\CandidateProfile\Infrastructure\Persistence\QueryCandidateDirectory;
use Src\CandidateProfile\Infrastructure\Persistence\QueryCandidateRepository;
use Src\CandidateProfile\Infrastructure\Persistence\QueryCandidateSettingsRepository;
use Src\CandidateProfile\Infrastructure\Persistence\QueryResumeRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

class CandidateProfileServiceProvider extends ContextServiceProvider
{
    public array $bindings = [
        CandidateRepository::class => QueryCandidateRepository::class,
        CandidateSettingsRepository::class => QueryCandidateSettingsRepository::class,
        ResumeRepository::class => QueryResumeRepository::class,
        CandidateDirectory::class => QueryCandidateDirectory::class,
        AccountRepository::class => QueryAccountRepository::class,
    ];

    public function register(): void
    {
        $this->app->tag(CandidateProfileProjection::class, 'user-profile-projections');
    }
}
