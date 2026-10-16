<?php

namespace Src\Job\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Job\Application\JobTags;
use Src\Job\Domain\WorkMode;

/**
 * Create (`POST`, the title is required) or change (`PATCH`, only what is sent changes, a null clears an optional field) a job. This checks only the
 * shape of what arrived; whether the ids exist and the location is one chain is checked by the use case, and completeness when the job is published.
 * Field names are the API's snake case names. A new job of an ERP tenant may name the `requisition_id` it is opened for (required when the tenant's setting says so).
 */
final class SaveJobRequest extends FormRequest
{
    private const int DESCRIPTION_MAX = 20000;

    private const int SKILLS_MAX = 15;

    private const int BENEFITS_MAX = 15;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $sometimes = $this->isMethod('POST') ? [] : ['sometimes'];

        return [
            'title' => [...$sometimes, 'required', 'string', 'min:3', 'max:150'],
            'requisition_id' => [$this->isMethod('POST') ? 'sometimes' : 'prohibited', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'role_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'job_type_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'job_level_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'work_time_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'experience_level_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'education_level_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'vacancies' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999999'],
            'salary_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999999999'],
            'salary_period_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'salary_visible' => ['sometimes', 'required', 'boolean'],
            'work_mode' => ['sometimes', 'nullable', 'string', Rule::enum(WorkMode::class)],
            'province_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subdistrict_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'village_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'postal_code_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'skill_ids' => ['sometimes', 'array', 'max:'.self::SKILLS_MAX],
            'skill_ids.*' => ['integer', 'min:1', 'distinct'],
            'tags' => ['sometimes', 'array', 'max:'.(JobTags::MAX_TAGS * 3)],
            'tags.*' => ['string', 'max:100'],
            'benefit_ids' => ['sometimes', 'array', 'max:'.self::BENEFITS_MAX],
            'benefit_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * The validated fields as content property changes (camel case names, typed values). The title is part of it for a PATCH; for a POST use
     * `title()` and `fields()`.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $validated = $this->validated();
        $changes = [];

        foreach (['title' => 'title', 'description' => 'description', 'address_line' => 'addressLine'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field];
            }
        }

        foreach ([
            'category_id' => 'categoryId', 'role_id' => 'roleId', 'job_type_id' => 'jobTypeId', 'job_level_id' => 'jobLevelId', 'work_time_id' => 'workTimeId',
            'experience_level_id' => 'experienceLevelId', 'education_level_id' => 'educationLevelId', 'vacancies' => 'vacancies', 'salary_min' => 'salaryMin',
            'salary_max' => 'salaryMax', 'salary_period_id' => 'salaryPeriodId', 'province_id' => 'provinceId', 'city_id' => 'cityId',
            'subdistrict_id' => 'subdistrictId', 'village_id' => 'villageId', 'postal_code_id' => 'postalCodeId',
        ] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : (int) $validated[$field];
            }
        }

        if (array_key_exists('deadline', $validated)) {
            $changes['deadline'] = $validated['deadline'] === null ? null : new DateTimeImmutable((string) $validated['deadline'], new DateTimeZone('UTC'));
        }

        if (array_key_exists('salary_visible', $validated)) {
            $changes['salaryVisible'] = (bool) $validated['salary_visible'];
        }

        if (array_key_exists('work_mode', $validated)) {
            $changes['workMode'] = $validated['work_mode'] === null ? null : WorkMode::from((string) $validated['work_mode']);
        }

        foreach (['skill_ids' => 'skillIds', 'benefit_ids' => 'benefitIds'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = array_map('intval', $validated[$field]);
            }
        }

        if (array_key_exists('tags', $validated)) {
            $changes['tags'] = array_values(array_map('strval', $validated['tags']));
        }

        return $changes;
    }

    public function requisitionId(): ?int
    {
        return $this->has('requisition_id') ? $this->integer('requisition_id') : null;
    }

    public function title(): string
    {
        return trim($this->string('title')->toString());
    }

    /** The changes without the title, for a new job. @return array<string, mixed> */
    public function fields(): array
    {
        $changes = $this->changes();
        unset($changes['title']);

        return $changes;
    }
}
