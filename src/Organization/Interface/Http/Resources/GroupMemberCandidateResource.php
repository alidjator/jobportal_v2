<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Roles\GroupMemberCandidate;

/**
 * An account that can be given a group role: its id and name and the tenant it belongs to, nothing else.
 *
 * @property GroupMemberCandidate $resource
 */
final class GroupMemberCandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource->userId,
            'name' => $this->resource->name,
            'company_id' => $this->resource->companyId,
            'company_name' => $this->resource->companyName,
        ];
    }
}
