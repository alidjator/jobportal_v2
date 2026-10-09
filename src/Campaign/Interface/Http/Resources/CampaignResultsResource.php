<?php

namespace Src\Campaign\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Campaign\Application\CampaignResults;

/** The counts a campaign brought; never the people. @property CampaignResults $resource */
final class CampaignResultsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'registrations' => $this->resource->registrations,
            'applicants' => $this->resource->applicants,
            'applications' => $this->resource->applications,
            'from' => $this->resource->from,
            'to' => $this->resource->to,
            'registrations_daily' => $this->resource->registrationsDaily,
        ];
    }
}
