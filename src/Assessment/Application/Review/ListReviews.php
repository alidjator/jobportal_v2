<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportStatus;
use Src\Assessment\Domain\ReportSummary;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The review queue of the psychologist's provider: the reports of its batteries, open ones first when filtered by `pending_review`. */
final readonly class ListReviews
{
    public function __construct(
        private ReviewAccess $access,
        private ReportRepository $reports,
        private ApplicantContacts $accounts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private ReviewClaims $claims,
        private Clock $clock,
    ) {}

    /**
     * @return Page<ReviewListItem>
     *
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     */
    public function execute(Actor $actor, ?ReportStatus $status, ?bool $reviewed, PageRequest $request, ?string $claimed = null): Page
    {
        $psychologist = $this->access->psychologist($actor);
        $page = $this->reports->pageOfProvider($psychologist->providerId, $status, $reviewed, $request, $claimed, $psychologist->id);
        $names = $this->accounts->names(array_values(array_unique(array_map(static fn (ReportSummary $summary): int => $summary->applicantUserId, $page->items))));

        $titles = $this->jobs->titles(array_values(array_unique(array_filter(array_map(static fn (ReportSummary $summary): ?int => $summary->jobId, $page->items)))));
        $companies = $this->companies->summaries(array_values(array_unique(array_filter(array_map(static fn (ReportSummary $summary): ?int => $summary->companyId, $page->items)))));

        $live = $this->clock->now()->modify(Report::CLAIM_LAPSE);
        $holders = $this->claims->holders($psychologist, array_map(static fn (ReportSummary $summary): ?int => $summary->claimTouchedAt !== null && $summary->claimTouchedAt > $live ? $summary->claimedByPsychologistId : null, $page->items));

        return new Page(
            array_map(static fn (ReportSummary $summary): ReviewListItem => new ReviewListItem(
                $summary,
                $names[$summary->applicantUserId] ?? '',
                $summary->jobId === null ? null : ($titles[$summary->jobId] ?? null),
                $summary->companyId === null ? null : ($companies[$summary->companyId]->name ?? null),
                $summary->claimedByPsychologistId === null || $summary->claimTouchedAt === null || $summary->claimTouchedAt <= $live ? null : ($holders[$summary->claimedByPsychologistId] ?? null),
            ), $page->items),
            $page->total,
            $page->request,
        );
    }
}
