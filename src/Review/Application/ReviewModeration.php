<?php

namespace Src\Review\Application;

use Src\Review\Domain\Events\ReviewModerated;
use Src\Review\Domain\ReplyRepository;
use Src\Review\Domain\ReplyStatus;
use Src\Review\Domain\Report;
use Src\Review\Domain\ReportRepository;
use Src\Review\Domain\ReportStatus;
use Src\Review\Domain\ReviewQuery;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The moderator's queues and the decision on a report. The routes already require `reviews.moderate`; every method also checks that the actor is an admin. */
final readonly class ReviewModeration
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReplyRepository $replies,
        private ReportRepository $reports,
        private ReviewViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return Page<ReviewView>
     *
     * @throws AccessDenied `admin-only`
     */
    public function reviews(Actor $actor, ReviewQuery $query, PageRequest $request): Page
    {
        $this->gate->adminId($actor);
        $page = $this->reviews->page($query, $request);

        return new Page($this->views->of($page->items, false, true), $page->total, $request);
    }

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     */
    public function review(Actor $actor, int $reviewId): AdminReviewDetail
    {
        $this->gate->adminId($actor);
        $review = $this->reviews->find($reviewId) ?? throw EntityNotFound::of('Review', $reviewId);

        return new AdminReviewDetail($this->views->one($review, false, true), $this->reports->forReview($reviewId));
    }

    /**
     * @return Page<ReplyItem>
     *
     * @throws AccessDenied `admin-only`
     */
    public function replies(Actor $actor, ?ReplyStatus $status, PageRequest $request): Page
    {
        $this->gate->adminId($actor);
        $page = $this->replies->page($status, $request);
        $items = [];

        foreach ($page->items as $reply) {
            $review = $this->reviews->find($reply->reviewId);

            if ($review !== null) {
                $items[] = new ReplyItem($reply, $review);
            }
        }

        return new Page($items, $page->total, $request);
    }

    /**
     * @return Page<ReportItem>
     *
     * @throws AccessDenied `admin-only`
     */
    public function reports(Actor $actor, ReportStatus $status, PageRequest $request): Page
    {
        $this->gate->adminId($actor);
        $page = $this->reports->page($status, $request);

        return new Page(array_map(fn (Report $report): ReportItem => new ReportItem($report, $this->reviews->find($report->reviewId)), $page->items), $page->total, $request);
    }

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `report-not-open`
     */
    public function dismiss(Actor $actor, int $reportId, ?string $ipAddress, ?string $userAgent): void
    {
        $adminId = $this->gate->adminId($actor);
        $now = $this->clock->now();

        $companyId = $this->transactions->run(function () use ($reportId, $adminId, $now): int {
            $report = $this->reports->find($reportId, true) ?? throw EntityNotFound::of('Report', $reportId);
            $this->reports->update($report->dismissed($adminId, $now));

            $review = $this->reviews->find($report->reviewId) ?? throw EntityNotFound::of('Review', $report->reviewId);

            return $review->companyId;
        });

        $this->events->dispatch([new ReviewModerated(ReviewModerated::REPORT_DISMISSED, $reportId, $companyId, $adminId, null, $ipAddress, $userAgent, $now)]);
    }
}
