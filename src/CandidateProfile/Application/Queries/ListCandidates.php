<?php

namespace Src\CandidateProfile\Application\Queries;

use Src\CandidateProfile\Domain\CandidateDirectory;

/** GET /candidates (publik). */
class ListCandidates
{
    public function __construct(private CandidateDirectory $directory) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return list<array<string,mixed>>
     */
    public function __invoke(array $filters, int $perPage, ?int $companyUserId): array
    {
        return $this->directory->search($filters, $perPage, $companyUserId);
    }
}
