<?php

namespace Src\Job\Application\Browse;

use Src\Job\Application\JobVisibility;
use Src\Job\Application\Matching\ScoreJobsForSeeker;
use Src\Job\Domain\Job;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The public page of a job. Only a live job is shown: `active`, its deadline day not over (Jakarta time, checked here so the page does not wait for the hourly
 * expiry), and its company active and not hidden. In every other case, and when there is no such job, the answer is the same `EntityNotFound`.
 * For a job seeker (`$viewerId`) the page also says whether they saved the job and, with a complete profile, how well they match it.
 */
final readonly class GetPublicJob
{
    /** API field => [content property, master URL name]. */
    private const array CLASSIFICATIONS = [
        'category' => ['categoryId', 'job-categories'],
        'role' => ['roleId', 'job-roles'],
        'job_type' => ['jobTypeId', 'job-types'],
        'job_level' => ['jobLevelId', 'job-levels'],
        'work_time' => ['workTimeId', 'work-times'],
        'experience_level' => ['experienceLevelId', 'experience-levels'],
        'education_level' => ['educationLevelId', 'education-levels'],
    ];

    public function __construct(
        private JobRepository $jobs,
        private PublicCompanies $companies,
        private ReferenceValues $references,
        private LocationReferences $locations,
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
        $content = $job->content;

        $classification = [];

        foreach (self::CLASSIFICATIONS as $field => [$property, $master]) {
            $classification[$field] = $this->named($master, $content->{$property}, $language);
        }

        $view = new PublicJobView(
            $job,
            $company,
            $classification,
            $this->salary($job, $language),
            $this->locations->names($content->provinceId, $content->cityId, $content->subdistrictId, $content->villageId, $content->postalCodeId),
            $this->namedList('skills', $content->skillIds, $language),
            $this->namedList('benefits', $content->benefitIds, $language),
        );

        if ($viewerId === null) {
            return $view;
        }

        $scores = $this->scores->forJobs($viewerId, [$jobId]);

        return $view->withMatch($scores[$jobId] ?? null)->withSaved($this->saved->savedAmong($viewerId, [$jobId]) !== []);
    }

    /** @return array{min: ?int, max: ?int, period: array{id: int, name: string}|null}|null */
    private function salary(Job $job, string $language): ?array
    {
        $content = $job->content;

        if (! $content->salaryVisible || ! $content->hasSalaryAmount()) {
            return null;
        }

        return ['min' => $content->salaryMin, 'max' => $content->salaryMax, 'period' => $this->named('salary-periods', $content->salaryPeriodId, $language)];
    }

    /** @return array{id: int, name: string}|null */
    private function named(string $master, ?int $id, string $language): ?array
    {
        $name = $id === null ? null : $this->references->name($master, $id, $language);

        return $id === null || $name === null ? null : ['id' => $id, 'name' => $name];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string}>
     */
    private function namedList(string $master, array $ids, string $language): array
    {
        $items = [];

        foreach ($ids as $id) {
            $item = $this->named($master, $id, $language);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
