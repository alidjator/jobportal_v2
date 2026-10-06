<?php

namespace Src\Job\Application\Admin;

use Src\Job\Application\JobDetail;
use Src\Shared\Application\Company\CompanySummary;

/** A job as an admin sees it: everything the company sees, and which company it is. */
final readonly class AdminJobDetail
{
    public function __construct(
        public JobDetail $detail,
        public ?CompanySummary $company,
    ) {}
}
