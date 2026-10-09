<?php

namespace Src\Messaging\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Messaging\Application\ConversationView;
use Src\Messaging\Application\MessagePayload;

/** @property ConversationView $resource */
final class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource->conversation;

        return [
            'id' => $row->id,
            'company' => ['id' => $row->companyId, 'name' => $this->resource->companyName],
            'job_seeker' => ['id' => $row->jobSeekerUserId, 'name' => $this->resource->jobSeekerName],
            'closed' => $row->closedAt !== null,
            'closed_at' => $row->closedAt?->format(DATE_ATOM),
            'last_message_at' => $row->lastMessageAt?->format(DATE_ATOM),
            'unread' => $row->unread,
            'last_message' => $this->resource->lastMessage === null ? null : MessagePayload::of($this->resource->lastMessage),
            // Only the job seeker is told whether the company is online.
            'company_online' => $this->when($this->resource->companyOnline !== null, $this->resource->companyOnline),
        ];
    }
}
