<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\Shared\Application\Matching\MatchScore;

/** A candidate recommended for a job: the list row, how well they fit, and whether the company already invited them to the job. */
final readonly class RecommendedCandidate
{
    public function __construct(public CandidateListItem $candidate, public MatchScore $match, public bool $invited) {}
}
