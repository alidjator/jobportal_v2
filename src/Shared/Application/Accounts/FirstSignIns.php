<?php

namespace Src\Shared\Application\Accounts;

use DateTimeImmutable;

/** The job seeker accounts that have signed in, for the jobs that follow up on a first sign-in. Provided by Identity. */
interface FirstSignIns
{
    /**
     * The ids of the active job seeker accounts with a verified email whose first sign-in was at or before the cutoff, ascending, after the cursor id. Anonymised
     * accounts and accounts that never signed in are left out.
     *
     * @return list<int>
     */
    public function jobSeekerIdsBefore(DateTimeImmutable $cutoff, int $afterId, int $limit): array;

    /**
     * When each of these accounts (any type) signed in for the first time; an account that never did is missing.
     *
     * @param  list<int>  $userIds
     * @return array<int, DateTimeImmutable>
     */
    public function firstSignedInAt(array $userIds): array;
}
