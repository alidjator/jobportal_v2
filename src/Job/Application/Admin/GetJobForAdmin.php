<?php

namespace Src\Job\Application\Admin;

use Src\Job\Application\JobDetailBuilder;
use Src\Job\Application\Review\SuspiciousContentScanner;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A platform admin reads any job, whatever its status and also while its company is suspended, with the flags the scanner found and whether the company is verified. */
final readonly class GetJobForAdmin
{
    public function __construct(
        private JobRepository $jobs,
        private JobDetailBuilder $details,
        private CompanyDirectory $companies,
        private SuspiciousContentScanner $scanner,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the job does not exist
     */
    public function execute(Actor $actor, int $jobId, string $language): AdminJobDetail
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can read the jobs of every company.');
        }

        $job = $this->jobs->find($jobId) ?? throw EntityNotFound::of('Job', $jobId);

        return new AdminJobDetail(
            $this->details->build($job, $language),
            $this->companies->summaries([$job->companyId])[$job->companyId] ?? null,
            $this->companies->isVerified($job->companyId),
            $this->scanner->flags($job->content),
        );
    }
}
