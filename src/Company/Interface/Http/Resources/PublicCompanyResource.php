<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Profile\PublicCompanyView;

/** The public company page. No contact data, no ids of other contexts; the classifications and places are names. @property PublicCompanyView $resource */
final class PublicCompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'name' => $view->name,
            'slug' => $view->slug,
            'bio' => $view->bio,
            'vision' => $view->vision,
            'website' => $view->website,
            'established_on' => $view->establishedOn?->format('Y-m-d'),
            'industry' => $view->industry,
            'organization_type' => $view->organizationType,
            'team_size' => $view->teamSize,
            'location' => [
                'address_line' => $view->addressLine,
                'village' => $view->location->village,
                'subdistrict' => $view->location->subdistrict,
                'city' => $view->location->city,
                'province' => $view->location->province,
                'postal_code' => $view->location->postalCode,
            ],
            'verified' => $view->verifiedAt !== null,
            'verified_at' => $view->verifiedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
