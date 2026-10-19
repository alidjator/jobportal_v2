<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Validation\Rule;
use Src\Company\Domain\CompanyLocation;
use Src\Company\Domain\LocationType;

/**
 * The fields of a work location, shared by the create request (name and type required) and the edit request (only what is sent). The address ids are checked as one chain by the use case
 * (422 `invalid-location`), and the coordinates as a pair inside Indonesia (422 `invalid-coordinates`).
 */
final class CompanyLocationFields
{
    /** @return array<string, list<mixed>> */
    public static function rules(bool $partial): array
    {
        $presence = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$presence, 'string', 'max:'.CompanyLocation::NAME_MAX],
            'type' => [...$presence, 'string', Rule::enum(LocationType::class)],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'province_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subdistrict_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'village_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'postal_code_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
        ];
    }
}
