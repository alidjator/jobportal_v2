<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Company\Application\Locations\CompanyLocationInput;
use Src\Company\Domain\LocationType;
use Src\Shared\Domain\Address;

/** A new work location: name and type are required, everything else is optional. */
final class CreateCompanyLocationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return CompanyLocationFields::rules(partial: false);
    }

    public function location(): CompanyLocationInput
    {
        $validated = $this->validated();
        $number = static fn (string $key): ?int => isset($validated[$key]) ? (int) $validated[$key] : null;

        return new CompanyLocationInput(
            (string) $validated['name'],
            LocationType::from((string) $validated['type']),
            new Address(
                isset($validated['address_line']) ? (string) $validated['address_line'] : null,
                $number('province_id'), $number('city_id'), $number('subdistrict_id'), $number('village_id'), $number('postal_code_id'),
            ),
            isset($validated['phone']) ? (string) $validated['phone'] : null,
            isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            isset($validated['longitude']) ? (float) $validated['longitude'] : null,
        );
    }
}
