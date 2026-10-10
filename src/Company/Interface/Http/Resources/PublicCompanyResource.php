<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Src\Company\Application\Profile\PublicCompanyView;

/**
 * The public company page. No contact data, no ids of other contexts; the classifications and places are names.
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
