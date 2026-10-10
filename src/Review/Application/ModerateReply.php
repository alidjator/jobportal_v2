<?php

namespace Src\Review\Application;

use Src\Review\Domain\Events\ReviewModerated;
use Src\Review\Domain\ModerationReason;
use Src\Review\Domain\Reply;
use Src\Review\Domain\ReplyRepository;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** An admin publishes a company's waiting reply, rejects it with a reason, or deletes a reply (the company may write a new one). The review's author hears of a published reply. */
final readonly class ModerateReply
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReplyRepository $replies,
        private ReviewNotifier $notifier,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `reply-not-pending`
     */
    public function approve(Actor $actor, int $reviewId, ?string $ipAddress, ?string $userAgent): void
    {
        $adminId = $this->gate->adminId($actor);
        $companyId = $this->change($reviewId, fn (Reply $reply): Reply => $reply->approved($adminId, $this->clock->now()));
        $review = $this->reviews->find($reviewId);

        if ($review !== null) {
            $this->notifier->author($review->userId, ReviewNotificationType::REPLY_PUBLISHED, $reviewId, $companyId);
        }

        $this->record(ReviewModerated::REPLY_APPROVED, $reviewId, $companyId, $adminId, null, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `reply-not-pending`
     */
    public function reject(Actor $actor, int $reviewId, ModerationReason $reason, ?string $note, ?string $ipAddress, ?string $userAgent): void
    {
        $adminId = $this->gate->adminId($actor);
        $companyId = $this->change($reviewId, fn (Reply $reply): Reply => $reply->rejected($reason, $note, $adminId, $this->clock->now()));
        $this->record(ReviewModerated::REPLY_REJECTED, $reviewId, $companyId, $adminId, $reason->value, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     */
    public function remove(Actor $actor, int $reviewId, ModerationReason $reason, ?string $ipAddress, ?string $userAgent): void
    {
        $adminId = $this->gate->adminId($actor);
        $companyId = $this->transactions->run(function () use ($reviewId): int {
            $review = $this->reviews->find($reviewId, true);

            if ($review === null || $this->replies->find($reviewId, true) === null) {
                throw EntityNotFound::of('Reply', $reviewId);
            }

            $this->replies->delete($reviewId);

            return $review->companyId;
        });
        $this->record(ReviewModerated::REPLY_REMOVED, $reviewId, $companyId, $adminId, $reason->value, $ipAddress, $userAgent);
    }

    /** @param  callable(Reply): Reply  $change */
    private function change(int $reviewId, callable $change): int
    {
        return $this->transactions->run(function () use ($reviewId, $change): int {
            $review = $this->reviews->find($reviewId, true);
            $reply = $this->replies->find($reviewId, true);

            if ($review === null || $reply === null) {
                throw EntityNotFound::of('Reply', $reviewId);
            }

            $this->replies->save($change($reply));

            return $review->companyId;
        });
    }

    private function record(string $action, int $reviewId, int $companyId, int $adminId, ?string $reason, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events->dispatch([new ReviewModerated($action, $reviewId, $companyId, $adminId, $reason, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
