<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\BlockedCompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The companies the job seeker blocked, the most recent first. A company that was suspended or hidden since is still listed, so the block can be lifted. */
final readonly class ListMyBlockedCompanies
{
    public function __construct(private JobSeekerGuard $guard, private BlockedCompanyRepository $blocked, private CompanyDirectory $companies) {}

    /**
     * @return list<BlockedCompany>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): array
    {
        $rows = $this->blocked->forUser($this->guard->userId($actor));
        $companies = $this->companies->summaries(array_map(static fn (array $row): int => $row['company_id'], $rows));
        $list = [];

        foreach ($rows as $row) {
            $company = $companies[$row['company_id']] ?? null;

            if ($company !== null) {
                $list[] = new BlockedCompany($company->id, $company->name, $company->slug, $row['blocked_at']);
            }
        }

        return $list;
    }
}
