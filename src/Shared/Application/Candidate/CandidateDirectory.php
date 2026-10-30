<?php

namespace Src\Shared\Application\Candidate;

/** Lets other contexts look up candidates (job seekers who opted in to be found) without reading JobSeeker's tables. Provided by JobSeeker. */
interface CandidateDirectory
{
    /**
     * The candidate behind the slug, only when they can be found by the company right now (opted in, active account, complete profile, and they did not block the
     * company); null otherwise.
     */
    public function findable(int $companyId, string $slug): ?CandidateRef;

    /**
     * Those of these accounts that can be found right now, under the same rule as `findable`. Without a company, a block does not count: the question is whether the person
     * can be found at all (a talent pool entry is kept when its owner blocked the company; it only stops being available to it).
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function findableIds(array $userIds, ?int $companyId = null): array;

    /**
     * The summaries of those of these accounts that the company can find right now (the rule of `findable`), with the names of master values in `$language`; one read for the whole list.
     *
     * @param  list<int>  $userIds
     * @return array<int, CandidateSummary> by account id
     */
    public function summaries(array $userIds, string $language, int $companyId): array;

    /**
     * The slug and name of these accounts, found or not (a company keeps seeing whom it invited); an account without a profile slug or that was anonymised is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, CandidateRef> by account id
     */
    public function refs(array $userIds): array;

    /**
     * The display name of these accounts, found or not, and nothing else (no slug, no profile): a company that holds a private list of people the import carried over sees whom it saved. An account
     * without a profile, a suspended or deactivated one, or one that was anonymised is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, string> by account id
     */
    public function names(array $userIds): array;
}
