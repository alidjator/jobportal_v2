<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Application\Candidates\ProfileViewEntry;

/** A company that opened the candidate's profile or resume. @property ProfileViewEntry $resource */
final class ProfileViewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'company' => ['id' => $this->resource->companyId, 'name' => $this->resource->companyName],
            'kind' => $this->resource->kind,
            'day' => $this->resource->day,
            'viewed_at' => $this->resource->viewedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
