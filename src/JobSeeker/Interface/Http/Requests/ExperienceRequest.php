<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A work experience, to add (every required field is needed) or to change (only the fields that are sent). Months are `YYYY-MM`. The content rules (lengths,
 * the end after the start, current or not) are checked on the whole entry by the use case, which answers 422 `invalid-experience` with `errors` per field.
 */
final class ExperienceRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? ['required'] : ['sometimes'];

        return [
            'job_title' => [...$presence, 'string', 'max:255'],
            'company_name' => [...$presence, 'string', 'max:255'],
            'job_type_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'location_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => [...$presence, 'date_format:Y-m'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'is_current' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * The validated fields as property changes (camel case names, typed values).
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $validated = $this->validated();
        $changes = [];

        foreach (['job_title' => 'jobTitle', 'company_name' => 'companyName', 'location_text' => 'locationText', 'description' => 'description'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field];
            }
        }

        if (array_key_exists('job_type_id', $validated)) {
            $changes['jobTypeId'] = $validated['job_type_id'] === null ? null : (int) $validated['job_type_id'];
        }

        foreach (['start_date' => 'startDate', 'end_date' => 'endDate'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : new DateTimeImmutable($validated[$field].'-01', new DateTimeZone('UTC'));
            }
        }

        if (array_key_exists('is_current', $validated)) {
            $changes['isCurrent'] = (bool) $validated['is_current'];
        }

        return $changes;
    }
}
