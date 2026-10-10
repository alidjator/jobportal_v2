<?php

namespace Src\Review\Application;

use Src\Review\Domain\Report;

/** A review as the moderator sees it: the view (author name, reply in any state, open report count), every report on it, and the author's account id. */
final readonly class AdminReviewDetail
{
    /** @param  list<Report>  $reports */
    public function __construct(
        public ReviewView $view,
        public array $reports,
    ) {}
}
