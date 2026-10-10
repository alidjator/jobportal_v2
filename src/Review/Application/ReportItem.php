<?php

namespace Src\Review\Application;

use Src\Review\Domain\Report;
use Src\Review\Domain\Review;

/** A report with the review it is about, for the admin queue. */
final readonly class ReportItem
{
    public function __construct(
        public Report $report,
        public ?Review $review,
    ) {}
}
