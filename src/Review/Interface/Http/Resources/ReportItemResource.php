<?php

namespace Src\Review\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Review\Application\ReportItem;

/**
 * A report in the moderator's queue with the review it is about.
 *
 * @property ReportItem $resource
 */
final class ReportItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $review = $this->resource->review;

        return [
            ...ReportPayload::of($this->resource->report),
            'review' => $review === null ? null : [
                'id' => $review->id,
                'company_id' => $review->companyId,
                'status' => $review->status->value,
                'title' => $review->content->title,
                'body' => $review->content->body,
            ],
        ];
    }
}
