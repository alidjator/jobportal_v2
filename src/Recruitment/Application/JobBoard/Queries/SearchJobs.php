<?php

namespace Src\Recruitment\Application\JobBoard\Queries;

use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\JobCatalog;
use Src\Recruitment\Domain\JobBoard\JobFilters;
use Src\Recruitment\Domain\JobBoard\Page;

/** Daftar lowongan aktif (`/jobs`, `/candidate/jobs`); `$userId` opsional untuk flag bookmark. */
final class SearchJobs
{
    public function __construct(
        private readonly JobCatalog $catalog,
        private readonly CandidateJobActivity $activity,
    ) {}

    public function __invoke(JobFilters $filters, ?int $userId, int $perPage, int $page): Page
    {
        $candidateId = $userId !== null ? $this->activity->candidateIdForUser($userId) : null;

        return $this->catalog->search($filters, $candidateId, $perPage, $page);
    }
}
