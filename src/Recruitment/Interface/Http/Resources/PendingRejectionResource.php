<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\PendingRejection;

/**
 * A rejection that waits its undo window: the application, when it takes effect, who asked, and the batch it belongs to (null for a single rejection).
 *
 * @property PendingRejection $resource
 */
final class PendingRejectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->resource->applicationId,
            'commit_at' => $this->resource->commitAt->format(DateTimeInterface::ATOM),
            'requested_by' => $this->resource->requestedByUserId,
            'batch_id' => $this->resource->batchId,
        ];
    }
}
