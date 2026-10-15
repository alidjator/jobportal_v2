<?php

namespace Src\Job\Application\Admin;

use Src\Job\Application\Review\SuspiciousContentScanner;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The jobs of every company for admins holding `jobs.manage`; in the review queue the flagged jobs come first (`phase-8-platform-admin.md` section 2). A search text matches the title, and also the jobs of the companies whose name contains it
 * (at most 200 such companies are looked up, which is far more than a search should match).
 */
final readonly class ListJobsForAdmin
{
    private const int MAX_MATCHING_COMPANIES = 200;

    public function __construct(
        private AdminJobReader $reader,
        private CompanyDirectory $companies,
        private SuspiciousContentScanner $scanner,
    ) {}

    /**
     * @return Page<AdminJobListItem>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, ?JobStatus $status, ?int $companyId, ?string $search, AdminJobSort $sort, PageRequest $request): Page
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can list the jobs of every company.');
        }

        $searchCompanyIds = $search === null || $search === '' ? [] : $this->companies->idsByName($search, self::MAX_MATCHING_COMPANIES);
        $flaggedIds = $status === JobStatus::PendingReview ? $this->flaggedPendingIds() : null;
        $page = $this->reader->page(new AdminJobQuery($status, $companyId, $search, $searchCompanyIds, $sort, $flaggedIds ?? []), $request);

        $summaries = $this->companies->summaries(array_values(array_unique(array_map(static fn (AdminJobListItem $item): int => $item->companyId, $page->items))));

        return new Page(
            array_map(
                static fn (AdminJobListItem $item): AdminJobListItem => $flaggedIds === null
                    ? $item->withCompany($summaries[$item->companyId] ?? null)
                    : $item->withCompany($summaries[$item->companyId] ?? null)->withFlagged(in_array($item->id, $flaggedIds, true)),
                $page->items,
            ),
            $page->total,
            $page->request,
        );
    }

    /** @return list<int> the jobs waiting for review whose text the scanner flags (the queue is short, so all of it is scanned) */
    private function flaggedPendingIds(): array
    {
        $ids = [];

        foreach ($this->reader->pendingTexts() as $id => $texts) {
            if ($this->scanner->flagsOf($texts['title'], $texts['description']) !== []) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
