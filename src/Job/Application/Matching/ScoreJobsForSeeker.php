<?php

namespace Src\Job\Application\Matching;

use Src\Shared\Application\Matching\CandidateSignalSource;
use Src\Shared\Application\Matching\MatchScore;
use Src\Shared\Application\Matching\MatchScoring;

/** How well jobs fit a job seeker, for the public job list and the job page of a signed-in job seeker. */
final readonly class ScoreJobsForSeeker
{
    public function __construct(private CandidateSignalSource $candidates, private JobSignalReader $jobs, private MatchScoring $scoring) {}

    /**
     * @param  list<int>  $jobIds
     * @return array<int, MatchScore>|null by job id; null when the job seeker has no complete profile (nothing can be said), and a job without signals is missing
     */
    public function forJobs(int $userId, array $jobIds): ?array
    {
        $candidate = $this->candidates->forUsers([$userId])[$userId] ?? null;

        if ($candidate === null) {
            return null;
        }

        $scores = [];

        foreach ($this->jobs->forJobs($jobIds) as $jobId => $job) {
            $scores[$jobId] = $this->scoring->score($candidate, $job);
        }

        return $scores;
    }
}
