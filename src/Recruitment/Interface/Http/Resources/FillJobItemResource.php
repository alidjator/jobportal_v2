<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\BulkItemResult;

/**
 * What "mark as filled" did to one application: rejected, waiting its undo window, or skipped with a code.
 *
 * @property BulkItemResult $resource
 */
final class FillJobItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->resource->applicationId,
            /** `rejected`, `pending` for a rejection that waits its undo window (`commit_at` says until when), or `skipped`. */
            'status' => $this->resource->isPending() ? 'pending' : ($this->resource->wasMoved() ? 'rejected' : 'skipped'),
            'code' => $this->resource->code,
            /** Only for `pending`: when the rejection takes effect. */
            'commit_at' => $this->when($this->resource->isPending(), fn (): ?string => $this->resource->commitAt?->format(DateTimeInterface::ATOM)),
        ];
    }
}
