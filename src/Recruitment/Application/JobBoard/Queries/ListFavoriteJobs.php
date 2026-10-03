<?php

namespace Src\Recruitment\Application\JobBoard\Queries;

use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\Page;

final class ListFavoriteJobs
{
    public function __construct(private readonly CandidateJobActivity $activity) {}

    public function __invoke(int $userId, int $perPage, int $page): Page
    {
        $candidateId = $this->activity->candidateIdForUser($userId);

        return $candidateId === null
            ? new Page([], 0, $perPage, $page)
            : $this->activity->bookmarkedJobs($candidateId, $perPage, $page);
    }
}
