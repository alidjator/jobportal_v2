<?php

namespace Src\Messaging\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Messaging\Domain\MessageReport;

/**
 * One row of the admin report list: no names, no text.
 *
 * @property MessageReport $resource
 */
final class AdminMessageReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'conversation_id' => $this->resource->conversationId,
            'reporter_side' => $this->resource->reporterSide->value,
            'reason' => $this->resource->reason->value,
            'status' => $this->resource->status->value,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'decided_at' => $this->resource->decidedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
