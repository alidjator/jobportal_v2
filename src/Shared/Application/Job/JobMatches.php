<?php

namespace Src\Shared\Application\Job;

/** The answer of a `JobMatcher`: how many jobs match, and the ids of the newest ones up to the limit that was asked for. */
final readonly class JobMatches
{
    /** @param  list<int>  $jobIds */
    public function __construct(public int $total, public array $jobIds) {}
}
