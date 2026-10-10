<?php

namespace Src\Review\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Application\ReviewView;

/**
 * A published review for the company's members: like the public one, with the company's reply in whatever state it is in.
 *
 * @property ReviewView $resource
 */
final class CompanyReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $review = $this->resource->review;
        $reply = $this->resource->reply;

        return [
            'id' => $review->id,
            'rating' => $review->content->rating,
            'aspects' => ['culture' => $review->content->cultureRating, 'pay' => $review->content->payRating, 'hiring' => $review->content->hiringRating],
            'title' => $review->content->title,
            'body' => $review->content->body,
            'author' => ['name' => $this->resource->authorName],
            'published_at' => $review->publishedAt?->format(DateTimeInterface::ATOM),
            'reply' => $reply === null ? null : [
                'body' => $reply->body,
                'status' => $reply->status->value,
                'reason' => $reply->reason?->value,
                'published_at' => $reply->publishedAt?->format(DateTimeInterface::ATOM),
            ],
        ];
    }
}
