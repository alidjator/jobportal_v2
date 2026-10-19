<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Company\Domain\LocationType;

/** A partial update of a work location: only the fields that are sent change, and a null clears an optional field. */
final class UpdateCompanyLocationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return CompanyLocationFields::rules(partial: true);
    }

    /**
     * The validated fields as use case changes (camel case names, typed values).
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $validated = $this->validated();
        $changes = [];

        foreach (['name' => 'name', 'phone' => 'phone', 'address_line' => 'addressLine'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field];
            }
        }

        foreach (['province_id' => 'provinceId', 'city_id' => 'cityId', 'subdistrict_id' => 'subdistrictId', 'village_id' => 'villageId', 'postal_code_id' => 'postalCodeId'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : (int) $validated[$field];
            }
        }

        foreach (['latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $validated)) {
                $changes[$field] = $validated[$field] === null ? null : (float) $validated[$field];
            }
        }

        if (array_key_exists('type', $validated)) {
            $changes['type'] = LocationType::from((string) $validated['type']);
        }

        return $changes;
    }
}
