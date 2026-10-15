<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Pagination\Page;

/** A page of the public search with the order that was really used (`meta.sort`): the asked order may fall back, for example `match` without a complete profile. */
final readonly class SearchJobsResult
{
    /** @param  Page<PublicJobListItem>  $page */
    public function __construct(public Page $page, public JobSearchSort $sort) {}
}
