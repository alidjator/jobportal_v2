<?php

namespace Src\Recruitment\Application\JobManagement\Commands;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\PostingSettings;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Recruitment\Domain\JobManagement\JobMessages;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Recruitment\Domain\JobManagement\JobStatus;
use Src\Recruitment\Domain\JobManagement\JobStatusPolicy;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class ChangeJobStatus
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobRepository $jobs,
        private readonly PostingSettings $settings,
    ) {
        $this->companies = $companies;
    }

    public function __invoke(int $userId, string $slug, JobStatus $to): string
    {
        $job = $this->jobs->findOwnedBySlug($this->companyId($userId), $slug)
            ?? throw new EntityNotFound(JobMessages::get('job_not_found'));

        $from = JobStatus::tryFrom((string) $job['status']) ?? JobStatus::Pending;
        $deadlinePassed = $job['deadline'] !== null && $job['deadline'] < now()->toDateString();

        if (! JobStatusPolicy::canTransition($from, $to, $this->settings->jobAutoApproved(), $deadlinePassed)) {
            throw new BusinessRuleViolation(JobMessages::get('invalid_job_status'));
        }

        $this->jobs->update($job['id'], ['status' => $to->value]);

        return JobMessages::get('job_status_updated_successfully');
    }
}
