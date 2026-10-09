<?php

namespace Src\Shared\Application\Analytics;

/**
 * A daily summary table of one context. A day is rebuilt from the source tables, so running it again for any past day gives the same rows. A summary keeps only
 * counts and sums (they can be added up over any range); nothing in it points at a person.
 */
interface AnalyticsRollup
{
    public function name(): string;

    /** The first Jakarta day (`Y-m-d`) with anything to summarise, or null when there is nothing yet. */
    public function earliestDay(): ?string;

    /** Replaces the summary of one Jakarta day (`Y-m-d`). */
    public function rebuild(string $day): void;
}
