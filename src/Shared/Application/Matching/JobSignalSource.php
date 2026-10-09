<?php

namespace Src\Shared\Application\Matching;

/** The signals of jobs. Provided by Job. */
interface JobSignalSource
{
    /**
     * @param  list<int>  $jobIds
     * @return array<int, JobSignals> by job id; a job that does not exist is missing, whatever its status
     */
    public function forJobs(array $jobIds): array;

    /**
     * The newest live jobs that are remote or in the province (any live job when the province is null), at most `$limit`.
     *
     * @return list<JobSignals>
     */
    public function liveNear(?int $provinceId, int $limit): array;
}
