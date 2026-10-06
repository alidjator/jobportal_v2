<?php

namespace Src\Job\Application\Admin;

use Src\Job\Domain\JobStatus;

/**
 * What an admin asks of the job list. `search` matches the title whatever the letter case; `searchCompanyIds` are the companies whose name matches the same
 * text (found through Company), so a job also matches when its company's name does.
 */
final readonly class AdminJobQuery
{
    /** @param  list<int>  $searchCompanyIds */
    public function __construct(
        public ?JobStatus $status = null,
        public ?int $companyId = null,
        public ?string $search = null,
        public array $searchCompanyIds = [],
        public AdminJobSort $sort = AdminJobSort::UpdatedAtDescending,
    ) {}
}
