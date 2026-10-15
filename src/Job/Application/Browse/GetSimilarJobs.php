<?php

namespace Src\Job\Application\Browse;

use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * "Similar jobs" under the page of a job, and on the page of a job that is gone: up to 6 live jobs of public companies with the same role (else the same
 * category), the closest by place and level first, at most 2 of one company. The job itself may be live, closed or expired; a job that was taken down, is on a
 * report hold, was never published or belongs to a hidden company is the same `EntityNotFound` as a job that does not exist.
 */
final readonly class GetSimilarJobs
{
    public const int LIMIT = 6;

    public const int PER_COMPANY = 2;

    public function __construct(
        private JobRepository $jobs,
        private PublicCompanies $companies,
        private ReferenceValues $references,
        private JobSearchReader $reader,
        private JobSearchQueryBuilder $queries,
        private PublicJobListItems $items,
    ) {}

    /**
     * @return list<PublicJobListItem>
     *
     * @throws EntityNotFound
     */
    public function execute(int $jobId, string $language): array
    {
        $job = $this->jobs->find($jobId);

        if ($job === null || $job->isOnHold() || ! in_array($job->status, [JobStatus::Active, JobStatus::Closed, JobStatus::Expired], true) || $this->companies->card($job->companyId) === null) {
            throw EntityNotFound::of('Job', $jobId);
        }

        $content = $job->content;
        $levelIds = $content->jobLevelId === null ? [] : $this->references->neighbours('job-levels', $content->jobLevelId, 1);
        $subject = new SimilarJobsQuery($jobId, $content->roleId, $content->categoryId, $content->provinceId, $content->cityId, $levelIds);

        $hits = $this->reader->similarTo($this->queries->build(new JobSearchCriteria), $subject, self::LIMIT, self::PER_COMPANY);

        return $this->items->of($hits, $language);
    }
}
