<?php

namespace Src\Campaign\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Campaign\Application\CampaignView;

/**
 * A campaign with its status today, its registration link and its company (null for a platform campaign).
 *
 * @property CampaignView $resource
 */
final class CampaignResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $campaign = $this->resource->campaign;

        return [
            'id' => $campaign->id,
            'code' => $campaign->code,
            'name' => $campaign->name,
            'type' => $campaign->type->value,
            'status' => $this->resource->status->value,
            'is_active' => $campaign->active,
            'starts_on' => $campaign->startsOn,
            'ends_on' => $campaign->endsOn,
            'link' => $this->resource->link,
            'company' => $campaign->companyId === null ? null : ['id' => $campaign->companyId, 'name' => $this->resource->companyName],
            'created_at' => $campaign->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $campaign->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
