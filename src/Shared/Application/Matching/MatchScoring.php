<?php

namespace Src\Shared\Application\Matching;

/** Scores how well a job seeker fits a job. Provided by Matching. */
interface MatchScoring
{
    public function score(CandidateSignals $candidate, JobSignals $job): MatchScore;
}
