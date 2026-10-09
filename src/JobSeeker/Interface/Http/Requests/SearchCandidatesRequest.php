<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobSeeker\Application\Candidates\CandidateSearchQuery;
use Src\JobSeeker\Domain\Availability;
use Src\JobSeeker\Domain\Gender;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[q]` (a piece of the headline, the summary or a job title), `filter[skill_id][]` and `filter[language_id][]` (the candidate has all of them),
 * `filter[province_id]`, `filter[city_id]`, `filter[experience_level_id][]`, `filter[education_level_id]` (this level or higher), `filter[availability][]`,
 * `filter[expected_salary_max]` with `filter[salary_period_id]`, `filter[religion_id][]`, `filter[nationality_id][]`, `filter[age_min]`, `filter[age_max]`,
 * `filter[gender]`, `page` and `per_page`. Any other filter is refused with 422.
 */
final class SearchCandidatesRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,skill_id,language_id,province_id,city_id,experience_level_id,education_level_id,availability,expected_salary_max,salary_period_id,religion_id,nationality_id,age_min,age_max,gender'],
            'filter.q' => ['nullable', 'string', 'min:2', 'max:100'],
            'filter.skill_id' => ['nullable', 'array', 'max:10'],
            'filter.skill_id.*' => ['integer', 'min:1'],
            'filter.language_id' => ['nullable', 'array', 'max:5'],
            'filter.language_id.*' => ['integer', 'min:1'],
            'filter.province_id' => ['nullable', 'integer', 'min:1'],
            'filter.city_id' => ['nullable', 'integer', 'min:1'],
            'filter.experience_level_id' => ['nullable', 'array', 'max:20'],
            'filter.experience_level_id.*' => ['integer', 'min:1'],
            'filter.education_level_id' => ['nullable', 'integer', 'min:1'],
            'filter.availability' => ['nullable', 'array', 'max:3'],
            'filter.availability.*' => ['string', Rule::enum(Availability::class)],
            'filter.expected_salary_max' => ['nullable', 'integer', 'min:1', 'required_with:filter.salary_period_id'],
            'filter.salary_period_id' => ['nullable', 'integer', 'min:1', 'required_with:filter.expected_salary_max'],
            'filter.religion_id' => ['nullable', 'array', 'max:20'],
            'filter.religion_id.*' => ['integer', 'min:1'],
            'filter.nationality_id' => ['nullable', 'array', 'max:20'],
            'filter.nationality_id.*' => ['integer', 'min:1'],
            'filter.age_min' => ['nullable', 'integer', 'min:13', 'max:100'],
            'filter.age_max' => ['nullable', 'integer', 'min:13', 'max:100', Rule::when($this->filled('filter.age_min'), ['gte:filter.age_min'])],
            'filter.gender' => ['nullable', 'string', Rule::enum(Gender::class)],
        ];
    }

    public function toQuery(): CandidateSearchQuery
    {
        $ids = fn (string $key): array => array_values(array_unique(array_map('intval', (array) $this->input("filter.{$key}", []))));
        $int = fn (string $key): ?int => $this->filled("filter.{$key}") ? $this->integer("filter.{$key}") : null;

        return new CandidateSearchQuery(
            $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null,
            $ids('skill_id'),
            $ids('language_id'),
            $int('province_id'),
            $int('city_id'),
            $ids('experience_level_id'),
            $int('education_level_id'),
            array_values(array_unique(array_map(static fn (string $value): Availability => Availability::from($value), (array) $this->input('filter.availability', [])), SORT_REGULAR)),
            $int('expected_salary_max'),
            $int('salary_period_id'),
            $ids('religion_id'),
            $ids('nationality_id'),
            $int('age_min'),
            $int('age_max'),
            $this->filled('filter.gender') ? Gender::from($this->string('filter.gender')->toString()) : null,
        );
    }
}
