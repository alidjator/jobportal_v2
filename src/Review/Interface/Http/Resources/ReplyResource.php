<?php

namespace Src\Review\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Domain\Reply;

/** The company's reply to a review with its state. @property Reply $resource */
final class ReplyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'review_id' => $this->resource->reviewId,
            'body' => $this->resource->body,
            'status' => $this->resource->status->value,
            'reason' => $this->resource->reason?->value,
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
