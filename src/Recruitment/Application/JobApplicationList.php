<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Pagination\Page;

/** A page of the applications of a job, and the pipeline columns (every stage of the company, in order) with their counts over all the job's applications. */
final readonly class JobApplicationList
{
    /**
     * @param  Page<RecruiterApplicationRow>  $page
     * @param  list<StageCount>  $stages
     */
    public function __construct(public Page $page, public array $stages) {}
}
