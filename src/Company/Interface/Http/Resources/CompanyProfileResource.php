<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Domain\Company;

/** A company's own profile, as stored: ids for the classifications and the location, Markdown as written. @property Company $resource */
final class CompanyProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->resource->profile();

        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug(),
            'status' => $this->resource->status()->value,
            'name' => $profile->name,
            'website' => $profile->website,
            'established_on' => $profile->establishedOn?->format('Y-m-d'),
            'bio' => $profile->bio,
            'vision' => $profile->vision,
            'industry_id' => $profile->industryId,
            'organization_type_id' => $profile->organizationTypeId,
            'team_size_id' => $profile->teamSizeId,
            'address_line' => $profile->addressLine,
            'province_id' => $profile->provinceId,
            'city_id' => $profile->cityId,
            'subdistrict_id' => $profile->subdistrictId,
            'village_id' => $profile->villageId,
            'postal_code_id' => $profile->postalCodeId,
            'contact_email' => $profile->contactEmail,
            'contact_phone' => $profile->contactPhone,
            'is_visible' => $profile->isVisible,
            'verified_at' => $this->resource->verifiedAt()?->format(DateTimeInterface::ATOM),
        ];
    }
}
