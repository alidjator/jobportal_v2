<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Domain\NameChangeRequest;

/**
 * A name change request as the company sees it. The decision note is the admin's reason for a rejection; which admin decided is never shown.
 *
 * @property NameChangeRequest $resource
 */
final class NameChangeRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'current_name' => $this->resource->currentName,
            'new_name' => $this->resource->newName,
            'reason' => $this->resource->reason,
            'document_name' => $this->resource->documentName,
            'status' => $this->resource->status->value,
            'decision_note' => $this->resource->decisionNote,
            'created_at' => $this->resource->createdAt?->format(DateTimeInterface::ATOM),
            'decided_at' => $this->resource->decidedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
