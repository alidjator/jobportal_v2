<?php

namespace Src\Matching\Application;

use Src\Shared\Application\Matching\ApplicantMatchScores;
use Src\Shared\Application\Matching\CandidateSignalSource;
use Src\Shared\Application\Matching\JobSignalSource;
use Src\Shared\Application\Matching\MatchScoring;

/** Scores one job seeker against one job with the same rules as the recommendations. */
final readonly class ScoreApplicant implements ApplicantMatchScores
{
    public function __construct(
        private CandidateSignalSource $candidates,
        private JobSignalSource $jobs,
        private MatchScoring $scoring,
    ) {}

    public function percent(int $userId, int $jobId): ?int
    {
        $candidate = $this->candidates->forUsers([$userId])[$userId] ?? null;
        $job = $this->jobs->forJobs([$jobId])[$jobId] ?? null;

        if ($candidate === null || $job === null) {
            return null;
        }

        return $this->scoring->score($candidate, $job)->percent;
    }
}
