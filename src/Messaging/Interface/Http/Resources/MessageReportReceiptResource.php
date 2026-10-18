<?php

namespace Src\Messaging\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Messaging\Domain\MessageReport;

/** What the reporter gets back: the report and nothing about what happens next. @property MessageReport $resource */
final class MessageReportReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'conversation_id' => $this->resource->conversationId,
            'reason' => $this->resource->reason->value,
            'status' => $this->resource->status->value,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
