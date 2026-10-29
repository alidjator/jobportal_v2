<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\CurrentCompany;

/** @property CurrentCompany $resource */
final class CurrentCompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name(),
                'slug' => $company->slug(),
                'status' => $company->status()->value,
            ],
            'role' => $this->resource->role->value,
            'is_owner' => $this->resource->isOwner,
            'permissions' => $this->resource->permissions,
            /** The group role of the member: the group and the group permissions the role holds; null without a role (or in a suspended group) */
            'group' => $this->resource->group,
            /** Whether the member takes part in approvals (asked for one, holds or held a place in one, or has one waiting): the approval pages are theirs even without an approval permission */
            'takes_part_in_approvals' => $this->resource->takesPartInApprovals,
        ];
    }
}
