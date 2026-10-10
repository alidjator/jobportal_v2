<?php

namespace Src\Review\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Application\ReviewView;

/**
 * A review as its author sees it: in any state, with the moderator's reason and note when it was rejected or taken down.
 *
 * @property ReviewView $resource
 */
final class MyReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $review = $this->resource->review;
        $reply = $this->resource->reply;

        return [
            'id' => $review->id,
            'company_id' => $review->companyId,
            'status' => $review->status->value,
            'rating' => $review->content->rating,
            'aspects' => ['culture' => $review->content->cultureRating, 'pay' => $review->content->payRating, 'hiring' => $review->content->hiringRating],
            'title' => $review->content->title,
            'body' => $review->content->body,
            'rejection_reason' => $review->reason?->value,
            'rejection_note' => $review->note,
            'reply' => $reply === null ? null : ['body' => $reply->body, 'status' => $reply->status->value, 'published_at' => $reply->publishedAt?->format(DateTimeInterface::ATOM)],
            'published_at' => $review->publishedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $review->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $review->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
