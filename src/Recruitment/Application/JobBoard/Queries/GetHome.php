<?php

namespace Src\Recruitment\Application\JobBoard\Queries;

use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\HomeCatalog;

final class GetHome
{
    public function __construct(
        private readonly HomeCatalog $home,
        private readonly CandidateJobActivity $activity,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(?int $userId): array
    {
        return $this->home->home($userId !== null ? $this->activity->candidateIdForUser($userId) : null);
    }
}
