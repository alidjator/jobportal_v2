<?php

namespace Src\JobSeeker\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\JobSeeker\Application\ApplicantProfileReader;
use Src\JobSeeker\Application\Candidates\CandidateReader;
use Src\JobSeeker\Application\Candidates\JobSeekerCandidateDirectory;
use Src\JobSeeker\Application\JobSeekerSettings;
use Src\JobSeeker\Domain\EducationRepository;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\JobSeekerDataEraser;
use Src\JobSeeker\Domain\ProfileListRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ProfileViewRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\JobSeeker\Infrastructure\Listeners\EraseJobSeekerDataWhenUserAnonymized;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseCandidateReader;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseEducationRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseExperienceRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseJobSeekerDataEraser;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseProfileListRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseProfileRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseProfileViewRepository;
use Src\JobSeeker\Infrastructure\Persistence\DatabaseResumeRepository;
use Src\Shared\Application\Applicant\ApplicantProfileSource;
use Src\Shared\Application\Candidate\CandidateDirectory;
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
        JobSeekerDataEraser::class => DatabaseJobSeekerDataEraser::class,
        ApplicantProfileSource::class => ApplicantProfileReader::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...JobSeekerSettings::definitions());
        });
    }

    public function boot(): void
    {
        RateLimiter::for(self::CANDIDATE_SEARCH_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::CANDIDATE_REQUESTS_PER_MINUTE)->by('candidate-search|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Event::listen(UserAnonymized::class, EraseJobSeekerDataWhenUserAnonymized::class);

        parent::boot();
    }
}
