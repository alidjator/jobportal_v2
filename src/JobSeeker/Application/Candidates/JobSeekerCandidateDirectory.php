<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Candidate\CandidateRef;

/** Gives other contexts the candidates of the search: who can be found by slug, and the names of the ones they already know. */
final readonly class JobSeekerCandidateDirectory implements CandidateDirectory
{
    public function __construct(private CandidateReader $candidates) {}

    public function findable(string $slug): ?CandidateRef
    {
        $row = $this->candidates->findBySlug($slug);

        return $row === null ? null : new CandidateRef($row->userId, $row->slug, $row->name);
    }

    public function findableIds(array $userIds): array
    {
        return $this->candidates->findableUserIds($userIds);
    }

    public function refs(array $userIds): array
    {
        $refs = [];

        foreach ($this->candidates->namesOfUsers($userIds) as $userId => $row) {
            $refs[$userId] = new CandidateRef($userId, $row['slug'], $row['name']);
        }

        return $refs;
    }
}
