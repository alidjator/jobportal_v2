<?php

namespace Src\Job\Application\Admin;

use Src\Job\Application\JobDetail;
use Src\Job\Application\Review\JobFlag;
use Src\Shared\Application\Company\CompanySummary;

/** A job as an admin sees it: everything the company sees, which company it is, whether that company is verified, and what made the job look suspicious. */
final readonly class AdminJobDetail
{
    /** @param  list<JobFlag>  $flags */
    public function __construct(
        public JobDetail $detail,
        public ?CompanySummary $company,
        public bool $companyVerified,
        public array $flags,
    ) {}
}
