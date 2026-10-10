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
}
