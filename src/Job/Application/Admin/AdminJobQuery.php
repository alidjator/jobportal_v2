<?php

namespace Src\Job\Application\Admin;

use Src\Job\Domain\JobStatus;

/**
 * What an admin asks of the job list. `search` matches the title whatever the letter case; `searchCompanyIds` are the companies whose name matches the same
 * text (found through Company), so a job also matches when its company's name does.
 */
final readonly class AdminJobQuery
{
    /**
     * @param  list<int>  $searchCompanyIds
     * @param  list<int>  $flaggedFirstIds  jobs listed before all others (the flagged jobs of the review queue)
     */
    public function __construct(
        public ?JobStatus $status = null,
        public ?int $companyId = null,
        public ?string $search = null,
        public array $searchCompanyIds = [],
        public AdminJobSort $sort = AdminJobSort::UpdatedAtDescending,
        public array $flaggedFirstIds = [],
    ) {}
}
