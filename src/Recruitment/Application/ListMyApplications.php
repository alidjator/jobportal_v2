<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The applicant's own applications, newest first. A job or company that no longer exists leaves its name empty; the application stays. */
final readonly class ListMyApplications
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicantApplicationReader $reader,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
    ) {}

    /**
     * @return CursorPage<ApplicationSummary>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor, ?StageType $status, CursorRequest $request): CursorPage
    {
        $page = $this->reader->list($this->guard->userId($actor), $status, $request);

        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (ApplicationRow $row): int => $row->jobId, $page->items))));
        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (ApplicationRow $row): int => $row->companyId, $page->items))));

        return new CursorPage(
            array_map(static fn (ApplicationRow $row): ApplicationSummary => new ApplicationSummary($row, $titles[$row->jobId] ?? null, ($companies[$row->companyId] ?? null)?->name), $page->items),
            $page->nextCursor,
            $page->perPage,
        );
    }
}
