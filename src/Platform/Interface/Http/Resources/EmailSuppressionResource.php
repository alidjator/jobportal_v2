<?php

namespace Src\Platform\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Application\EmailSuppression\EmailSuppression;

/** @property EmailSuppression $resource */
final class EmailSuppressionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'reason' => $this->resource->reason->value,
            'source' => $this->resource->source,
            'details' => $this->resource->details,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
