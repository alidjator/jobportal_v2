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
        ];
    }
}
