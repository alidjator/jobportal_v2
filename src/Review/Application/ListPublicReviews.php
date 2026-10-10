<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReviewRepository;
use Src\Review\Domain\ReviewSort;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** What anyone sees on a company's page: its published reviews with the author's name and the published reply, and the summary. */
final readonly class ListPublicReviews
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReviewSummaries $summaries,
        private ReviewViews $views,
    ) {}

    /** @throws EntityNotFound the company does not exist or is hidden */
    public function execute(string $companySlug, ReviewSort $sort, PageRequest $request): ReviewList
    {
        $company = $this->gate->publicCompany($companySlug);
        $page = $this->reviews->publishedPage($company->id, $sort, $request);

        return new ReviewList(new Page($this->views->of($page->items, true), $page->total, $request), $this->summaries->forCompany($company->id));
    }
}
