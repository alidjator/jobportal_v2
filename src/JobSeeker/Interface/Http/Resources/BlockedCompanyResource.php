<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Application\BlockedCompany;

/**
 * A company the job seeker blocked.
 *
 * @property BlockedCompany $resource
 */
final class BlockedCompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'company' => ['id' => $this->resource->companyId, 'name' => $this->resource->name, 'slug' => $this->resource->slug],
            'blocked_at' => $this->resource->blockedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
