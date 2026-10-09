<?php

namespace Src\Messaging\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Messaging\Application\BulkMessageResult;

/** @property BulkMessageResult $resource */
final class BulkMessageResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->resource->applicationId,
            'status' => $this->resource->status,
            'code' => $this->resource->reason,
            'conversation_id' => $this->resource->conversationId,
        ];
    }
}
