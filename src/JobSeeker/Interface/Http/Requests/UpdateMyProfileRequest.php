<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobSeeker\Domain\Availability;
use Src\JobSeeker\Domain\Gender;
use Src\JobSeeker\Domain\MaritalStatus;

/**
 * A partial update: only the fields that are sent are validated and changed. A null clears an optional field. Field names are the API's snake case names.
 * A phone number is an optional `+` and 8 to 15 digits; spaces, dashes, dots and brackets typed in it are removed first.
 */
final class UpdateMyProfileRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::enum(Gender::class)],
            'marital_status' => ['sometimes', 'nullable', 'string', Rule::enum(MaritalStatus::class)],
            'religion_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'nationality_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'expected_salary' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000000'],
            'expected_salary_period_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'experience_level_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'availability' => ['sometimes', 'nullable', 'string', Rule::enum(Availability::class)],
            'available_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'province_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subdistrict_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'village_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'postal_code_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:100'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_discoverable' => ['sometimes', 'required', 'boolean'],
            'resume_discoverable' => ['sometimes', 'required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['phone', 'whatsapp'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/[\s\-().]/', '', $this->string($field)->toString())]);
            }
        }
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
        $utc = new DateTimeZone('UTC');

        foreach (['phone' => 'phone', 'whatsapp' => 'whatsapp', 'address_line' => 'addressLine', 'headline' => 'headline', 'summary' => 'summary'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field];
            }
        }

        foreach ([
            'religion_id' => 'religionId', 'nationality_id' => 'nationalityId', 'expected_salary' => 'expectedSalary', 'expected_salary_period_id' => 'expectedSalaryPeriodId',
            'experience_level_id' => 'experienceLevelId', 'province_id' => 'provinceId', 'city_id' => 'cityId', 'subdistrict_id' => 'subdistrictId',
            'village_id' => 'villageId', 'postal_code_id' => 'postalCodeId',
        ] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : (int) $validated[$field];
            }
        }

        foreach (['birth_date' => 'birthDate', 'available_from' => 'availableFrom'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : new DateTimeImmutable((string) $validated[$field], $utc);
            }
        }

        foreach (['gender' => [Gender::class, 'gender'], 'marital_status' => [MaritalStatus::class, 'maritalStatus'], 'availability' => [Availability::class, 'availability']] as $field => [$enum, $property]) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : $enum::from((string) $validated[$field]);
            }
        }

        foreach (['is_discoverable' => 'isDiscoverable', 'resume_discoverable' => 'resumeDiscoverable'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = (bool) $validated[$field];
            }
        }

        return $changes;
    }
}
