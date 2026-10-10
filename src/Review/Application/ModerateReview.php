<?php

namespace Src\Review\Application;

use Closure;
use Src\Review\Domain\Events\ReviewModerated;
use Src\Review\Domain\ModerationReason;
use Src\Review\Domain\ReportRepository;
use Src\Review\Domain\ReportStatus;
use Src\Review\Domain\Review;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An admin with `reviews.moderate` publishes a waiting review, rejects it with a reason, or takes a published one down (which also settles the open reports on it).
 * The author is told; the company admins hear of a review when it is published.
 */
final readonly class ModerateReview
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReportRepository $reports,
        private ReviewSummaries $summaries,
        private ReviewNotifier $notifier,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `review-not-pending`
     */
    public function approve(Actor $actor, int $reviewId, ?string $ipAddress, ?string $userAgent): Review
    {
        $review = $this->decide($actor, $reviewId, ReviewModerated::REVIEW_APPROVED, null, $ipAddress, $userAgent, fn (Review $review, int $adminId): Review => $review->approved($adminId, $this->clock->now()));
        $this->notifier->author($review->userId, ReviewNotificationType::PUBLISHED, (int) $review->id, $review->companyId);
        $this->notifier->companyAdmins($review->companyId, ReviewNotificationType::RECEIVED, (int) $review->id);

        return $review;
    }

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `review-not-pending`
     */
    public function reject(Actor $actor, int $reviewId, ModerationReason $reason, ?string $note, ?string $ipAddress, ?string $userAgent): Review
    {
        $review = $this->decide($actor, $reviewId, ReviewModerated::REVIEW_REJECTED, $reason, $ipAddress, $userAgent, fn (Review $review, int $adminId): Review => $review->rejected($reason, $note, $adminId, $this->clock->now()));
        $this->notifier->author($review->userId, ReviewNotificationType::REJECTED, (int) $review->id, $review->companyId, ['reason' => $reason->value]);

        return $review;
    }

    /**
     * @throws AccessDenied `admin-only`
     * @throws EntityNotFound
     * @throws StateConflict `review-not-published`
     */
    public function remove(Actor $actor, int $reviewId, ModerationReason $reason, ?string $note, ?string $ipAddress, ?string $userAgent): Review
    {
        $review = $this->decide($actor, $reviewId, ReviewModerated::REVIEW_REMOVED, $reason, $ipAddress, $userAgent, function (Review $review, int $adminId) use ($reason, $note): Review {
            $removed = $review->removed($reason, $note, $adminId, $this->clock->now());
            $this->reports->resolveOpen((int) $review->id, ReportStatus::Actioned, $adminId, $this->clock->now());

            return $removed;
        });
        $this->notifier->author($review->userId, ReviewNotificationType::REMOVED, (int) $review->id, $review->companyId, ['reason' => $reason->value]);

        return $review;
    }

    /** @param  Closure(Review, int): Review  $change */
    private function decide(Actor $actor, int $reviewId, string $action, ?ModerationReason $reason, ?string $ipAddress, ?string $userAgent, Closure $change): Review
    {
        $adminId = $this->gate->adminId($actor);

        [$updated, $wasPublic] = $this->transactions->run(function () use ($reviewId, $adminId, $change): array {
            $review = $this->reviews->find($reviewId, true) ?? throw EntityNotFound::of('Review', $reviewId);
            $updated = $change($review, $adminId);
            $this->reviews->update($updated);

            return [$updated, $review->isPublic()];
        });

        if ($wasPublic || $updated->isPublic()) {
            $this->summaries->forget($updated->companyId);
        }

        $this->events->dispatch([new ReviewModerated($action, (int) $updated->id, $updated->companyId, $adminId, $reason?->value, $ipAddress, $userAgent, $this->clock->now())]);

        return $updated;
    }
}
