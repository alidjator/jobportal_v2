<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Locations\CompanyLocationView;

/**
 * A work location of the own company: the address as ids (what the pickers need) and names, the phone, the coordinates (or null) and the time zone worked out from the province.
 *
 * @property CompanyLocationView $resource
 */
final class CompanyLocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $location = $this->resource->location;
        $address = $location->address;
        $names = $this->resource->names;

        return [
            'id' => $location->id,
            'name' => $location->name,
            'type' => $location->type->value,
            'is_main' => $location->isMain,
            'status' => $location->status->value,
            'address' => [
                'address_line' => $address->line,
                'province' => $address->provinceId === null ? null : ['id' => $address->provinceId, 'name' => $names->province],
                'city' => $address->cityId === null ? null : ['id' => $address->cityId, 'name' => $names->city],
                'subdistrict' => $address->subdistrictId === null ? null : ['id' => $address->subdistrictId, 'name' => $names->subdistrict],
                'village' => $address->villageId === null ? null : ['id' => $address->villageId, 'name' => $names->village],
                'postal_code' => $address->postalCodeId === null ? null : ['id' => $address->postalCodeId, 'code' => $names->postalCode],
            ],
            'phone' => $location->phone,
            'coordinates' => $location->coordinates === null ? null : ['latitude' => $location->coordinates->latitude, 'longitude' => $location->coordinates->longitude],
            /** The IANA zone of the province (`Asia/Jakarta`, `Asia/Makassar` or `Asia/Jayapura`); null without a province. */
            'time_zone' => $this->resource->timeZone,
        ];
    }
}
