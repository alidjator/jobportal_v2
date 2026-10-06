<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An education entry, to add (every required field is needed) or to change (only the fields that are sent). The GPA is a number from 0 to 4 with at most two
 * decimals. The content rules are checked on the whole entry by the use case, which answers 422 `invalid-education` with `errors` per field.
 */
final class EducationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? ['required'] : ['sometimes'];

        return [
            'education_level_id' => [...$presence, 'integer', 'min:1'],
            'institution' => [...$presence, 'string', 'max:500'],
            'field_of_study' => ['sometimes', 'nullable', 'string', 'max:500'],
            'start_year' => [...$presence, 'integer'],
            'end_year' => ['sometimes', 'nullable', 'integer'],
            'is_current' => ['sometimes', 'boolean'],
            'gpa' => ['sometimes', 'nullable', 'numeric'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The validated fields as property changes (camel case names, typed values). The GPA becomes a text with two decimals, or stays as typed when it is
     * not a number the rules accept, so the use case can refuse it with a message.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $validated = $this->validated();
        $changes = [];

        foreach (['institution' => 'institution', 'field_of_study' => 'fieldOfStudy', 'description' => 'description'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field];
            }
        }

        foreach (['education_level_id' => 'educationLevelId', 'start_year' => 'startYear', 'end_year' => 'endYear'] as $field => $property) {
            if (array_key_exists($field, $validated)) {
                $changes[$property] = $validated[$field] === null ? null : (int) $validated[$field];
            }
        }

        if (array_key_exists('gpa', $validated)) {
            $changes['gpa'] = $validated['gpa'] === null ? null : (string) $validated['gpa'];
        }

        if (array_key_exists('is_current', $validated)) {
            $changes['isCurrent'] = (bool) $validated['is_current'];
        }

        return $changes;
    }
}
