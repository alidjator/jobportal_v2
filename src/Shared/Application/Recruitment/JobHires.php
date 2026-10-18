<?php

namespace Src\Shared\Application\Recruitment;

/** Lets other contexts count the hires of a job without reading Recruitment's tables. Provided by Recruitment. */
interface JobHires
{
    /**
     * How many applications of each job are in a `hired` stage now.
     *
     * @param  list<int>  $jobIds
     * @return array<int, int> job id => count, jobs without a hire missing
     */
    public function countPerJob(array $jobIds): array;
}
