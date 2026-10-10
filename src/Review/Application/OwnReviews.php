<?php

namespace Src\Review\Application;

use Src\Review\Domain\Review;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds a review of the acting job seeker; someone else's review does not exist for them (404). */
final readonly class OwnReviews
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function find(Actor $actor, int $reviewId, bool $forUpdate = false): Review
    {
        $userId = $this->gate->jobSeekerId($actor);
        $review = $this->reviews->find($reviewId, $forUpdate);

        if ($review === null || $review->userId !== $userId) {
            throw EntityNotFound::of('Review', $reviewId);
        }

        return $review;
    }
}
