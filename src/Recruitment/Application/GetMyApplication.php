<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationJobSnapshots;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One of the applicant's own applications. Another person's application does not exist for the caller. */
final readonly class GetMyApplication
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicantApplicationReader $reader,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private ApplicationJobSnapshots $jobSnapshots,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id, string $language = 'id'): ApplicationView
    {
        $data = $this->reader->detail($this->guard->userId($actor), $id) ?? throw EntityNotFound::of('Application', $id);

        $snapshot = $this->jobSnapshots->find($id);

        return new ApplicationView(
            $data,
            $this->jobs->titles([$data->row->jobId])[$data->row->jobId] ?? null,
            ($this->companies->summaries([$data->row->companyId])[$data->row->companyId] ?? null)?->name,
            $snapshot === null ? null : LocalisedJobContent::of($snapshot, $language),
        );
    }
}
