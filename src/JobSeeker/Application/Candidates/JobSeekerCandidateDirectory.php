<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Candidate\CandidateRef;
use Src\Shared\Application\Candidate\CandidateSummary;

/** Gives other contexts the candidates of the search: who can be found by slug, and the names of the ones they already know. */
final readonly class JobSeekerCandidateDirectory implements CandidateDirectory
{
    private const int SKILLS = 5;

    public function __construct(private CandidateReader $candidates, private CandidateLabels $labels) {}

    public function findable(string $slug): ?CandidateRef
    {
        $row = $this->candidates->findBySlug($slug);

        return $row === null ? null : new CandidateRef($row->userId, $row->slug, $row->name, $row->avatarPath !== null);
    }

    public function findableIds(array $userIds): array
    {
        return $this->candidates->findableUserIds($userIds);
    }

    public function summaries(array $userIds, string $language): array
    {
        $rows = $this->candidates->findableRows($userIds);

        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (CandidateRow $row): int => $row->userId, $rows);
        $positions = $this->candidates->latestPositions($ids);
        $skillIds = $this->candidates->skillIdsOf($ids, self::SKILLS);
        $skillNames = $this->labels->names('skills', array_merge(...array_values($skillIds ?: [[]])), $language);
        $cities = $this->labels->cities(array_map(static fn (CandidateRow $row): ?int => $row->cityId, $rows));
        $summaries = [];

        foreach ($rows as $row) {
            $summaries[$row->userId] = new CandidateSummary(
                $row->userId,
                $row->slug,
                $row->name,
                $row->headline,
                $row->cityId === null ? null : ($cities[$row->cityId] ?? null),
                $positions[$row->userId] ?? null,
                array_values(array_filter(array_map(static fn (int $id): ?string => $skillNames[$id] ?? null, $skillIds[$row->userId] ?? []))),
                $row->avatarPath !== null,
            );
        }

        return $summaries;
    }

    public function refs(array $userIds): array
    {
        $refs = [];

        foreach ($this->candidates->namesOfUsers($userIds) as $userId => $row) {
            $refs[$userId] = new CandidateRef($userId, $row['slug'], $row['name'], $row['has_avatar']);
        }

        return $refs;
    }

    public function names(array $userIds): array
    {
        return $this->candidates->activeNamesOfUsers($userIds);
    }
}
