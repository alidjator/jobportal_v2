<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Src\Company\Application\Images\CompanyImageFile;
use Src\Company\Application\Profile\CompanyProfileView;

/**
 * A company's own profile, as stored: ids for the classifications, Markdown as written, and the main location in place of the address.
 *
 * @property CompanyProfileView $resource
 */
final class CompanyProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;
        $profile = $company->profile();

        return [
            'id' => $company->id,
            'slug' => $company->slug(),
            'status' => $company->status()->value,
            'name' => $profile->name,
            'website' => $profile->website,
            'established_on' => $profile->establishedOn?->format('Y-m-d'),
            'bio' => $profile->bio,
            'vision' => $profile->vision,
            'industry_id' => $profile->industryId,
            'organization_type_id' => $profile->organizationTypeId,
            'team_size_id' => $profile->teamSizeId,
            'contact_email' => $profile->contactEmail,
            'contact_phone' => $profile->contactPhone,
            'is_visible' => $profile->isVisible,
            'main_location' => $this->resource->mainLocation === null ? null : (new CompanyLocationResource($this->resource->mainLocation))->toArray($request),
            'verified_at' => $company->verifiedAt()?->format(DateTimeInterface::ATOM),
            'logo_url' => $this->imageUrl('logo', $company->logoPath()),
            'banner_url' => $this->imageUrl('banner', $company->bannerPath()),
        ];
    }

    /** The own route (it works while the company is hidden or suspended), with the version so a replaced picture breaks the cache. */
    private function imageUrl(string $kind, ?string $path): ?string
    {
        return $path === null ? null : URL::to("/api/v1/company/{$kind}").'?v='.CompanyImageFile::version($path);
    }
}
