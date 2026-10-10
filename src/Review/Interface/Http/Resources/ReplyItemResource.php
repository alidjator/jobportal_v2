<?php

namespace Src\Review\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Application\ReplyItem;

/**
 * A reply in the moderator's queue, with the title of the review it answers.
 *
 * @property ReplyItem $resource
 */
final class ReplyItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reply = $this->resource->reply;

        return [
            'review_id' => $reply->reviewId,
            'company_id' => $this->resource->review->companyId,
            'review_title' => $this->resource->review->content->title,
            'body' => $reply->body,
            'status' => $reply->status->value,
            'reason' => $reply->reason?->value,
            'note' => $reply->note,
            'created_at' => $reply->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $reply->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
