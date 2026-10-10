<?php

namespace Src\Review\Application;

use Src\Review\Domain\Review;
use Src\Review\Domain\ReviewContent;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author changes their review. Whatever its state it waits for an admin again (a published one disappears from the public list until then); a removed one is final. */
final readonly class EditReview
{
    public function __construct(
        private OwnReviews $own,
        private ReviewRepository $reviews,
        private ReviewSummaries $summaries,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `review-not-editable`
     */
    public function execute(Actor $actor, int $reviewId, ReviewContent $content): Review
    {
        [$updated, $wasPublic] = $this->transactions->run(function () use ($actor, $reviewId, $content): array {
            $review = $this->own->find($actor, $reviewId, true);
            $updated = $review->edited($content, $this->clock->now());
            $this->reviews->update($updated);

            return [$updated, $review->isPublic()];
        });

        if ($wasPublic) {
            $this->summaries->forget($updated->companyId);
        }

        return $updated;
    }
}
