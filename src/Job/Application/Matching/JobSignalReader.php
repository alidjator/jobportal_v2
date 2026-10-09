<?php

namespace Src\Job\Application\Matching;

use Src\Shared\Application\Matching\JobSignals;

/** Reads the signals of the matching engine from the job tables: the content of the job and the range and rank of its level and education. */
interface JobSignalReader
{
    /**
     * @param  list<int>  $jobIds
     * @return array<int, JobSignals> by job id, whatever the status of the job; an id without a job is missing
     */
    public function forJobs(array $jobIds): array;
}
