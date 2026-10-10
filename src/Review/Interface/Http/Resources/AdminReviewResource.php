<?php

namespace Src\Review\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Application\ReviewView;

/** A review in the moderator's list: the text, the author, the reply in any state and the number of open reports. @property ReviewView $resource */
final class AdminReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $review = $this->resource->review;
        $reply = $this->resource->reply;

        return [
            'id' => $review->id,
            'company_id' => $review->companyId,
            'author' => ['id' => $review->userId, 'name' => $this->resource->authorName],
            'status' => $review->status->value,
            'rating' => $review->content->rating,
            'aspects' => ['culture' => $review->content->cultureRating, 'pay' => $review->content->payRating, 'hiring' => $review->content->hiringRating],
            'title' => $review->content->title,
            'body' => $review->content->body,
            'reason' => $review->reason?->value,
            'note' => $review->note,
            'open_reports' => $this->resource->openReports,
            'reply' => $reply === null ? null : ['body' => $reply->body, 'status' => $reply->status->value, 'reason' => $reply->reason?->value, 'note' => $reply->note],
            'moderated_at' => $review->moderatedAt?->format(DateTimeInterface::ATOM),
            'published_at' => $review->publishedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $review->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $review->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
