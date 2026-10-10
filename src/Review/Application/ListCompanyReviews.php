<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReviewRepository;
use Src\Review\Domain\ReviewSort;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The published reviews of the member's own company, with the company's reply in whatever state it is in, and the summary. Any member of an active company may read them. */
final readonly class ListCompanyReviews
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReviewSummaries $summaries,
        private ReviewViews $views,
    ) {}

    /** @throws AccessDenied `not-a-company-member` or `company-suspended` */
    public function execute(Actor $actor, ReviewSort $sort, PageRequest $request): ReviewList
    {
        $companyId = $this->gate->memberCompanyId($actor, false);
        $page = $this->reviews->publishedPage($companyId, $sort, $request);

        return new ReviewList(new Page($this->views->of($page->items, false), $page->total, $request), $this->summaries->forCompany($companyId));
    }
}
