<?php

namespace Src\Shared\Application\Matching;

/** The signals of job seekers. Provided by JobSeeker. Only an active job seeker with a complete profile (the rule used for applying) has signals. */
interface CandidateSignalSource
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, CandidateSignals> by account id; an account without a complete profile is missing
     */
    public function forUsers(array $userIds): array;

    /**
     * The next accounts with signals after the id, in id order, for walking all job seekers in batches.
     *
     * @return list<int>
     */
    public function userIdsAfter(int $afterId, int $limit): array;
}
