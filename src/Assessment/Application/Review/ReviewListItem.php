<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ReportSummary;

/** One line of the review queue with the candidate's name (empty when the account was anonymised), the job they applied to and its company, to sort the work, and who holds the report. */
final readonly class ReviewListItem
{
    /** @param  array{id: int, name: string, mine: bool}|null  $claimedBy  the holder of the live claim */
    public function __construct(public ReportSummary $summary, public string $candidateName, public ?string $jobTitle = null, public ?string $companyName = null, public ?array $claimedBy = null) {}
}
