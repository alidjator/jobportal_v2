<?php

namespace Src\Recruitment\Application\JobBoard\Queries;

use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\JobCatalog;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Detail lowongan + lowongan terkait + resume kandidat yang sedang login.
 * Lowongan `pending` hanya terlihat oleh perusahaan pemiliknya.
 */
final class GetJobDetail
{
    public function __construct(
        private readonly JobCatalog $catalog,
        private readonly CandidateJobActivity $activity,
    ) {}

    /**
     * @return array{job: array<string, mixed>, related_jobs: list<array<string, mixed>>, resumes: list<array<string, mixed>>}
     */
    public function __invoke(string $slug, ?int $userId, ?string $userRole): array
    {
        $candidateId = $userId !== null ? $this->activity->candidateIdForUser($userId) : null;

        $job = $this->catalog->findBySlug($slug, $candidateId);

        if ($job === null) {
            throw new EntityNotFound('Job not found');
        }

        if ($job['status'] === 'pending' && ! ($userRole === 'company' && $userId !== null && $job['company_user_id'] === $userId)) {
            throw new EntityNotFound('Job not found');
        }

        return [
            'job' => $job,
            'related_jobs' => $this->catalog->related($job['id'], $job['category_id'], $candidateId),
            'resumes' => $userRole === 'candidate' && $candidateId !== null ? $this->catalog->resumesOf($candidateId) : [],
        ];
    }
}
