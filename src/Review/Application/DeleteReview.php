<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The author deletes their review, with the reply and the reports on it, whatever its state. */
final readonly class DeleteReview
{
    public function __construct(
        private OwnReviews $own,
        private ReviewRepository $reviews,
        private ReviewSummaries $summaries,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $reviewId): void
    {
        $review = $this->transactions->run(function () use ($actor, $reviewId) {
            $review = $this->own->find($actor, $reviewId, true);
            $this->reviews->delete((int) $review->id);

            return $review;
        });

        if ($review->isPublic()) {
            $this->summaries->forget($review->companyId);
        }
    }
}
