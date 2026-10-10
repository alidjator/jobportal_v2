<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Domain\Resume;

/**
 * One resume of the owner. The storage path is never shown.
 *
 * @property Resume $resource
 */
final class ResumeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'size_bytes' => $this->resource->sizeBytes,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
