<?php

namespace Src\Review\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Application\ReviewView;

/**
 * A published review as the public sees it: the ratings, the text, the author's name and the published reply.
 *
 * @property ReviewView $resource
 */
final class PublicReviewResource extends JsonResource
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
            'reply' => $reply === null ? null : ['body' => $reply->body, 'published_at' => $reply->publishedAt?->format(DateTimeInterface::ATOM)],
        ];
    }
}
