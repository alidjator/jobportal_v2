<?php

namespace Src\JobSeeker\Application\Candidates;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/**
 * Reads the candidates a company may find: a profile whose owner switched on `is_discoverable`, whose account is an active job seeker's (not suspended,
 * deactivated or anonymised) and which is complete enough to apply (phone, province and city, a resume, and an experience or an education entry).
 */
interface CandidateReader
{
    /**
     * @param  DateTimeImmutable  $today  the Jakarta date, to turn ages into birth dates
     * @return Page<CandidateRow> the most recently updated profile first
     */
    public function search(CandidateSearchQuery $query, PageRequest $request, DateTimeImmutable $today): Page;

    public function findBySlug(string $slug): ?CandidateRow;

    /**
     * Those of these accounts that can be found now (the rule of `findBySlug`).
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function findableUserIds(array $userIds): array;

    /**
     * The candidates among these accounts who can be found now (the rule of `findBySlug`), as the search reads them.
     *
     * @param  list<int>  $userIds
     * @return list<CandidateRow>
     */
    public function findableRows(array $userIds): array;

    /**
     * The current or latest position of each of these accounts (the order of the profile's experience list); an account without one is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{title: string, employer: string}> by account id
     */
    public function latestPositions(array $userIds): array;

    /**
     * The first skills of each of these accounts in the order the candidate gave them, at most `$perUser`; an account without skills is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, list<int>> skill ids by account id
     */
    public function skillIdsOf(array $userIds, int $perUser): array;

    /**
     * The most recently updated candidates who can be found, in the province (anywhere when it is null), at most `$limit`: the pool a job's recommendations
     * are scored from.
     *
     * @return list<CandidateRow>
     */
    public function pool(?int $provinceId, int $limit): array;

    /**
     * The slug and name of these accounts whether or not they can be found now; an account without a slug or whose account was anonymised is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{slug: string, name: string}> by account id
     */
    public function namesOfUsers(array $userIds): array;

    /**
     * The name of these accounts whether or not they can be found now, for those that are active and not anonymised and have a profile (a company holding a private list sees whom it saved).
     *
     * @param  list<int>  $userIds
     * @return array<int, string> by account id
     */
    public function activeNamesOfUsers(array $userIds): array;
}
