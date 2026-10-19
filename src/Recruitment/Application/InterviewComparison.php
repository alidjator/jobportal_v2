<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKit;
use Src\Shared\Application\Pagination\Page;

/**
 * The comparison of a job's interview results: the kit whose questions are the columns (null when no interview of the rows has a kit) and a page of rows.
 */
final readonly class InterviewComparison
{
    /** @param  Page<InterviewComparisonRow>  $rows */
    public function __construct(public ?InterviewKit $kit, public Page $rows) {}
}
