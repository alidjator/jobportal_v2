<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Src\Company\Application\Profile\PublicCompanyLocation;
use Src\Company\Application\Profile\PublicCompanyView;

/**
 * The public company page. No contact data, no ids of other contexts (its own `id` is public: the job resources show it too, and `GET jobs?filter[company_id]` needs it); the classifications and places are names. The active work locations are listed, the main one first.
 *
 * @property PublicCompanyView $resource
 */
final class PublicCompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'id' => $view->id,
            'name' => $view->name,
            'slug' => $view->slug,
            'bio' => $view->bio,
            'vision' => $view->vision,
            'website' => $view->website,
            'established_on' => $view->establishedOn?->format('Y-m-d'),
            'industry' => $view->industry,
            'organization_type' => $view->organizationType,
            'team_size' => $view->teamSize,
            'locations' => array_map(static fn (PublicCompanyLocation $location): array => [
                'name' => $location->name,
                'type' => $location->type->value,
                'is_main' => $location->isMain,
                'address' => [
                    'address_line' => $location->addressLine,
                    'village' => $location->names->village,
                    'subdistrict' => $location->names->subdistrict,
                    'city' => $location->names->city,
                    'province' => $location->names->province,
                    'postal_code' => $location->names->postalCode,
                ],
                'coordinates' => $location->coordinates === null ? null : ['latitude' => $location->coordinates->latitude, 'longitude' => $location->coordinates->longitude],
            ], $view->locations),
            'verified' => $view->verifiedAt !== null || $view->internal,
            'verified_at' => $view->verifiedAt?->format(DateTimeInterface::ATOM),
            'logo_url' => $this->imageUrl($view->slug, 'logo', $view->logoVersion),
            'banner_url' => $this->imageUrl($view->slug, 'banner', $view->bannerVersion),
        ];
    }

    /** The public route, with the version so a replaced picture breaks the cache. */
    private function imageUrl(string $slug, string $kind, ?string $version): ?string
    {
        return $version === null ? null : URL::to("/api/v1/companies/{$slug}/{$kind}").'?v='.$version;
    }
}
