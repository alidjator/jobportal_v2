<?php

namespace Src\Job\Application\Browse;

use Src\Job\Application\JobVisibility;
use Src\Job\Application\Matching\ScoreJobsForSeeker;
use Src\Job\Domain\Job;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The public page of a job. Only a live job is shown: `active`, its deadline day not over (Jakarta time, checked here so the page does not wait for the hourly
 * expiry), and its company active and not hidden. In every other case, and when there is no such job, the answer is the same `EntityNotFound`.
 * For a job seeker (`$viewerId`) the page also says whether they saved the job and, with a complete profile, how well they match it.
 */
final readonly class GetPublicJob
{
    public function __construct(
        private JobRepository $jobs,
        private PublicCompanies $companies,
        private PublicJobViewBuilder $views,
        private JobVisibility $visibility,
        private ScoreJobsForSeeker $scores,
        private SavedJobRepository $saved,
    ) {}

    /** @throws EntityNotFound */
    public function execute(int $jobId, string $language, ?int $viewerId = null): PublicJobView
    {
        $job = $this->jobs->find($jobId);

        if ($job === null || ! $this->visibility->isLive($job)) {
            throw EntityNotFound::of('Job', $jobId);
        }

        $company = $this->companies->card($job->companyId) ?? throw EntityNotFound::of('Job', $jobId);
        $view = $this->views->build($job, $company, $language);

        if ($viewerId === null) {
            return $view;
        }

        $scores = $this->scores->forJobs($viewerId, [$jobId]);

        if ($scores !== null) {
            $view = $view->withMatch($scores[$jobId] ?? null);
        }

        return $view->withSaved($this->saved->savedAmong($viewerId, [$jobId]) !== []);
    }
}
