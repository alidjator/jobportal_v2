<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Validation\Rule;
use Src\Organization\Application\Group\LegalEntityInput;
use Src\Organization\Domain\LegalForm;

/**
 * The fields of a legal entity as the API takes them, shared by the requests that create or edit one: `legal_name` (2 to 200 characters), `legal_form`, `npwp` (up to
 * 25), `nib` (up to 20), `address_line` and the five location ids. Whether the location ids form one matching chain is the use case's rule.
 */
trait LegalEntityFields
{
    private const array PROPERTY_BY_FIELD = [
        'legal_name' => 'legalName', 'legal_form' => 'legalForm', 'npwp' => 'npwp', 'nib' => 'nib', 'address_line' => 'addressLine',
        'province_id' => 'provinceId', 'city_id' => 'cityId', 'subdistrict_id' => 'subdistrictId', 'village_id' => 'villageId', 'postal_code_id' => 'postalCodeId',
    ];

    /**
     * @param  string  $prefix  where the fields sit in the body, for example `legal_entity.`
     * @param  bool  $required  whether the name and the form are required (a new entity) or only checked when sent (an edit)
     * @return array<string, mixed>
     */
    private function legalEntityRules(string $prefix = '', bool $required = true): array
    {
        $presence = $required ? ['required'] : ['sometimes'];

        return [
            "{$prefix}legal_name" => [...$presence, 'string', 'min:2', 'max:200'],
            "{$prefix}legal_form" => [...$presence, 'string', Rule::enum(LegalForm::class)],
            "{$prefix}npwp" => ['nullable', 'string', 'max:25'],
            "{$prefix}nib" => ['nullable', 'string', 'max:20'],
            "{$prefix}address_line" => ['nullable', 'string', 'max:255'],
            "{$prefix}province_id" => ['nullable', 'integer', 'min:1'],
            "{$prefix}city_id" => ['nullable', 'integer', 'min:1'],
            "{$prefix}subdistrict_id" => ['nullable', 'integer', 'min:1'],
            "{$prefix}village_id" => ['nullable', 'integer', 'min:1'],
            "{$prefix}postal_code_id" => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function legalEntityInput(string $prefix = ''): LegalEntityInput
    {
        $text = fn (string $field): ?string => $this->filled($prefix.$field) ? trim($this->string($prefix.$field)->toString()) : null;
        $id = fn (string $field): ?int => $this->filled($prefix.$field) ? $this->integer($prefix.$field) : null;

        return new LegalEntityInput(
            (string) $text('legal_name'), LegalForm::from($this->string($prefix.'legal_form')->toString()), $text('npwp'), $text('nib'), $text('address_line'),
            $id('province_id'), $id('city_id'), $id('subdistrict_id'), $id('village_id'), $id('postal_code_id'),
        );
    }

    /**
     * Only the fields the request holds, keyed by the property name of the entity; a field sent as null is cleared.
     *
     * @return array<string, mixed>
     */
    private function legalEntityChanges(): array
    {
        $changes = [];

        foreach (self::PROPERTY_BY_FIELD as $field => $property) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            $changes[$property] = match (true) {
                $value === null => null,
                $field === 'legal_form' => LegalForm::from((string) $value),
                str_ends_with($field, '_id') => (int) $value,
                default => trim((string) $value),
            };
        }

        return $changes;
    }
}
