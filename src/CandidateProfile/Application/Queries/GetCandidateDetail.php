<?php

namespace Src\CandidateProfile\Application\Queries;

use Src\CandidateProfile\Domain\CandidateDirectory;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** GET /candidates/{username} (publik). */
class GetCandidateDetail
{
    public function __construct(private CandidateDirectory $directory) {}

    /** @return array<string,mixed> */
    public function __invoke(string $username): array
    {
        return $this->directory->detail($username) ?? throw new EntityNotFound('Not Found');
    }
}
