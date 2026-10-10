<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReplyRepository;
use Src\Review\Domain\ReportRepository;
use Src\Review\Domain\Review;
use Src\Shared\Application\Applicant\ApplicantContacts;

/** Adds the author's name, the reply and, for admins, the open reports to reviews, with one lookup of each for the whole list. */
final readonly class ReviewViews
{
    public function __construct(
        private ApplicantContacts $contacts,
        private ReplyRepository $replies,
        private ReportRepository $reports,
    ) {}

    /**
     * @param  list<Review>  $reviews
     * @param  bool  $publicRepliesOnly  true for the public list: a reply that is still waiting or was rejected is left out
     * @param  bool  $withReports  true for admins
     * @return list<ReviewView>
     */
    public function of(array $reviews, bool $publicRepliesOnly, bool $withReports = false): array
    {
        $ids = array_map(static fn (Review $review): int => (int) $review->id, $reviews);
        $names = $this->contacts->names(array_values(array_unique(array_map(static fn (Review $review): int => $review->userId, $reviews))));
        $replies = $this->replies->forReviews($ids);
        $reports = $withReports ? $this->reports->openCounts($ids) : [];

        return array_map(static function (Review $review) use ($names, $replies, $reports, $publicRepliesOnly): ReviewView {
            $reply = $replies[(int) $review->id] ?? null;

            return new ReviewView(
                $review,
                $names[$review->userId] ?? ReviewView::DELETED_AUTHOR,
                $reply !== null && $publicRepliesOnly && ! $reply->isPublic() ? null : $reply,
                $reports[(int) $review->id] ?? 0,
            );
        }, $reviews);
    }

    public function one(Review $review, bool $publicRepliesOnly, bool $withReports = false): ReviewView
    {
        return $this->of([$review], $publicRepliesOnly, $withReports)[0];
    }
}
