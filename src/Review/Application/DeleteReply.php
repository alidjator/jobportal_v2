<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReplyRepository;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company admin deletes the company's reply to one of its reviews, in whatever state it is. */
final readonly class DeleteReply
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReplyRepository $replies,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound the review is not one of the company's, or has no reply
     */
    public function execute(Actor $actor, int $reviewId): void
    {
        $companyId = $this->gate->memberCompanyId($actor, true);
        $review = $this->reviews->find($reviewId);

        if ($review === null || $review->companyId !== $companyId || $this->replies->find($reviewId) === null) {
            throw EntityNotFound::of('Reply', $reviewId);
        }

        $this->replies->delete($reviewId);
    }
}
