<?php

namespace Src\Newsletter\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Newsletter\Domain\Issue;

/**
 * An issue with its sending figures.
 *
 * @property Issue $resource
 */
final class IssueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'subject' => $this->resource->subject,
            'body' => $this->resource->body,
            'status' => $this->resource->status->value,
            'created_by_admin_id' => $this->resource->createdByAdminId,
            'sent_by_admin_id' => $this->resource->sentByAdminId,
            'recipients' => $this->resource->recipients,
            'delivered' => $this->resource->delivered,
            'failed' => $this->resource->failed,
            'started_at' => $this->resource->startedAt?->format(DateTimeInterface::ATOM),
            'finished_at' => $this->resource->finishedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
