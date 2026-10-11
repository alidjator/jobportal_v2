<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ReportSummary;

/** One line of the review queue with the candidate's name (empty when the account was anonymised). */
final readonly class ReviewListItem
{
    public function __construct(public ReportSummary $summary, public string $candidateName) {}
}
