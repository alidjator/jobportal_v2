<?php

namespace Src\Shared\Application\Candidate;

/** Lets other contexts look up candidates (job seekers who opted in to be found) without reading JobSeeker's tables. Provided by JobSeeker. */
interface CandidateDirectory
{
    /** The candidate behind the slug, only when they can be found right now (opted in, active account, complete profile); null otherwise. */
    public function findable(string $slug): ?CandidateRef;

    /**
     * Those of these accounts that can be found right now, under the same rule as `findable`.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function findableIds(array $userIds): array;

    /**
     * The summaries of those of these accounts that can be found right now (the rule of `findable`), with the names of master values in `$language`; one read for the whole list.
     *
     * @param  list<int>  $userIds
     * @return array<int, CandidateSummary> by account id
     */
    public function summaries(array $userIds, string $language): array;

    /**
     * The slug and name of these accounts, found or not (a company keeps seeing whom it invited); an account without a profile slug or that was anonymised is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, CandidateRef> by account id
     */
    public function refs(array $userIds): array;
}
