<?php

namespace Src\Recruitment\Application\JobManagement\Queries;

use Carbon\Carbon;
use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\JobFormOptions;
use Src\Recruitment\Application\JobManagement\Ports\PostingSettings;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Recruitment\Domain\JobManagement\JobMessages;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class GetEditJobData
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobRepository $jobs,
        private readonly JobFormOptions $options,
        private readonly PostingSettings $settings,
    ) {
        $this->companies = $companies;
    }

    /** @return array<string, mixed> */
    public function __invoke(int $userId, string $slug): array
    {
        $job = $this->jobs->findOwnedBySlug($this->companyId($userId), $slug)
            ?? throw new EntityNotFound(JobMessages::get('job_not_found'));

        // Relasi `benefits` menimpa kolom teks `jobs.benefits` pada JSON (seperti serialisasi model lama).
        $job = array_merge($job, $this->jobs->tagsAndBenefits($job['id']));
        $startDay = (int) abs(Carbon::parse($job['created_at'])->diffInDays(now()));

        return ['job' => $job] + $this->options->forEdit() + [
            'start_day' => $startDay,
            'end_day' => $startDay + $this->settings->deadlineExpirationLimitDays(),
        ];
    }
}
