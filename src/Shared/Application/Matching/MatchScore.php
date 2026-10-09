<?php

namespace Src\Shared\Application\Matching;

/**
 * How well a job seeker fits a job. `percent` is 0 to 100, or null when too little is known about the job to say. `signals` holds the fit of each signal
 * (`skills`, `location`, `experience`, `education`, `salary`, `role`) as 0 to 100, or null for a signal that could not be judged.
 */
final readonly class MatchScore
{
    /** The lowest score that is recommended. */
    public const int RECOMMENDED = 40;

    /** @param  array<string, int|null>  $signals */
    public function __construct(public ?int $percent, public array $signals) {}

    public function isRecommended(): bool
    {
        return $this->percent !== null && $this->percent >= self::RECOMMENDED;
    }
}
