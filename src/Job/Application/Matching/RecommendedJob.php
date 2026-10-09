<?php

namespace Src\Job\Application\Matching;

use Src\Job\Application\Browse\PublicJobListItem;
use Src\Shared\Application\Matching\MatchScore;

/** A job recommended to a job seeker: the row of the public search and how well it fits. */
final readonly class RecommendedJob
{
    public function __construct(public PublicJobListItem $job, public MatchScore $match) {}
}
