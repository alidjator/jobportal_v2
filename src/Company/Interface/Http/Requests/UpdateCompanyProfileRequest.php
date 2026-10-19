<?php

namespace Src\Company\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A partial update: only the fields that are sent are validated and changed. A null clears an optional field; name, industry,
 * organization type and visibility cannot be cleared. Field names are the API's snake case names.
 */
final class UpdateCompanyProfileRequest extends FormRequest
{
    private const int TEXT_MAX = 5000;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:255'],
            'established_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.self::TEXT_MAX],
            'vision' => ['sometimes', 'nullable', 'string', 'max:'.self::TEXT_MAX],
            'industry_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'organization_type_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'team_size_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'contact_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'is_visible' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * The validated fields as profile property changes (camel case names, typed values).
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $validated = $this->validated();
        $changes = [];

        foreach ([
            'name' => 'name', 'website' => 'website', 'bio' => 'bio', 'vision' => 'vision',
            'contact_email' => 'contactEmail', 'contact_phone' => 'contactPhone',
        ] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field];
            }
        }

        foreach ([
            'industry_id' => 'industryId', 'organization_type_id' => 'organizationTypeId', 'team_size_id' => 'teamSizeId',
        ] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : (int) $validated[$field];
            }
        }

        if (array_key_exists('established_on', $validated)) {
            $changes['establishedOn'] = $validated['established_on'] === null
                ? null
                : new DateTimeImmutable((string) $validated['established_on'], new DateTimeZone('UTC'));
        }

        if (array_key_exists('is_visible', $validated)) {
            $changes['isVisible'] = (bool) $validated['is_visible'];
        }

        return $changes;
    }
}
