<?php

namespace Src\Review\Application;

use Src\Review\Domain\Reply;
use Src\Review\Domain\ReplyRepository;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company admin writes, or rewrites, the company's one reply to a published review. The reply waits for an admin before the public sees it. */
final readonly class WriteReply
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReplyRepository $replies,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws EntityNotFound the review is not a published review of the company
     */
    public function execute(Actor $actor, int $reviewId, string $body): Reply
    {
        $companyId = $this->gate->memberCompanyId($actor, true);

        return $this->transactions->run(function () use ($actor, $companyId, $reviewId, $body): Reply {
            $review = $this->reviews->find($reviewId, true);

            if ($review === null || $review->companyId !== $companyId || ! $review->isPublic()) {
                throw EntityNotFound::of('Review', $reviewId);
            }

            $now = $this->clock->now();
            $existing = $this->replies->find($reviewId, true);

            return $this->replies->save($existing === null ? Reply::write($reviewId, $body, (int) $actor->id, $now) : $existing->edited($body, (int) $actor->id, $now));
        });
    }
}
