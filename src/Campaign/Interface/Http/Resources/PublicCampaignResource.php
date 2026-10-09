<?php

namespace Src\Campaign\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Campaign\Application\PublicCampaign;

/** What the registration page shows about a running campaign. @property PublicCampaign $resource */
final class PublicCampaignResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;

        return [
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'type' => $this->resource->type->value,
            'company' => $company === null ? null : ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
        ];
    }
}
