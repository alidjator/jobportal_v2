<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\BulkItemResult;

/** @property BulkItemResult $resource */
final class BulkItemResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->resource->applicationId,
            /** `moved`, `skipped`, or `pending` for a rejection that waits its undo window (`commit_at` says until when). */
            'status' => $this->resource->isPending() ? 'pending' : ($this->resource->wasMoved() ? 'moved' : 'skipped'),
            'code' => $this->resource->code,
            /** Only for `pending`: when the rejection takes effect. */
            'commit_at' => $this->when($this->resource->isPending(), fn (): ?string => $this->resource->commitAt?->format(DateTimeInterface::ATOM)),
        ];
    }
}
