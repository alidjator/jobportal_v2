<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReviewSummary;
use Src\Shared\Application\Pagination\Page;

/** One page of a company's reviews with the summary of all of them. */
final readonly class ReviewList
{
    /** @param  Page<ReviewView>  $page */
    public function __construct(
        public Page $page,
        public ReviewSummary $summary,
    ) {}
}
