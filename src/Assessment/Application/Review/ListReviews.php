<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportStatus;
use Src\Assessment\Domain\ReportSummary;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The review queue of the psychologist's provider: the reports of its batteries, open ones first when filtered by `pending_review`. */
final readonly class ListReviews
{
    public function __construct(private ReviewAccess $access, private ReportRepository $reports, private ApplicantContacts $accounts) {}

    /**
     * @return Page<ReviewListItem>
     *
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     */
    public function execute(Actor $actor, ?ReportStatus $status, PageRequest $request): Page
    {
        $psychologist = $this->access->psychologist($actor);
        $page = $this->reports->pageOfProvider($psychologist->providerId, $status, $request);
        $names = $this->accounts->names(array_values(array_unique(array_map(static fn (ReportSummary $summary): int => $summary->applicantUserId, $page->items))));

        return new Page(
            array_map(static fn (ReportSummary $summary): ReviewListItem => new ReviewListItem($summary, $names[$summary->applicantUserId] ?? ''), $page->items),
            $page->total,
            $page->request,
        );
    }
}
