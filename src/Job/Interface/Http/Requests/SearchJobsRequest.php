<?php

namespace Src\Job\Interface\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Job\Application\Browse\JobSearchCriteria;
use Src\Job\Application\Browse\JobSearchSort;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Pagination\PageRequest;

/**
 * Allowed: `filter[q]` (at most 100 characters and 5 words), the master and place filters `filter[category_id]`, `role_id`, `job_type_id`, `job_level_id`,
 * `experience_level_id`, `education_level_id`, `work_time_id`, `province_id`, `city_id`, `skill_id` and `company_id` (each one id or up to 20 ids separated by
 * commas, matching any), `filter[work_mode]` (the same, with `onsite`, `hybrid`, `remote`), `filter[salary_min]` with `filter[salary_period_id]`, `filter[posted_within]` (1, 3, 7 or 30
 * days), `sort` (`relevance` by default with a keyword, else `-published_at`; also `published_at`, `deadline`, `-deadline`, `match`), `page` and `per_page` (1 to 50, default 20). Any other filter is refused with 422.
 */
final class SearchJobsRequest extends FormRequest
{
    public const int MAX_PER_PAGE = 50;

    private const int MAX_QUERY_LENGTH = 100;

    private const int MAX_WORDS = 5;

    private const int MAX_VALUES = 20;

    private const array ID_FILTERS = [
        'category_id', 'role_id', 'job_type_id', 'job_level_id', 'experience_level_id', 'education_level_id', 'work_time_id', 'province_id', 'city_id', 'skill_id', 'company_id',
    ];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'sort' => ['nullable', 'string', Rule::enum(JobSearchSort::class)],
            'filter' => ['nullable', 'array:q,work_mode,salary_min,salary_period_id,posted_within,'.implode(',', self::ID_FILTERS)],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_QUERY_LENGTH, $this->maxWords()],
            'filter.work_mode' => ['nullable', 'string', $this->listOf(array_map(static fn (WorkMode $mode): string => $mode->value, WorkMode::cases()))],
            'filter.salary_min' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'filter.salary_period_id' => ['nullable', 'integer', 'min:1'],
            'filter.posted_within' => ['nullable', 'integer', Rule::in(JobSearchCriteria::POSTED_WITHIN)],
        ];

        foreach (self::ID_FILTERS as $filter) {
            $rules["filter.{$filter}"] = ['nullable', 'string', $this->listOf(null)];
        }

        return $rules;
    }

    public function pageRequest(): PageRequest
    {
        return new PageRequest($this->integer('page', 1), $this->integer('per_page', PageRequest::DEFAULT_PER_PAGE));
    }

    public function criteria(): JobSearchCriteria
    {
        return new JobSearchCriteria(
            $this->words(),
            $this->ids('category_id'),
            $this->ids('role_id'),
            $this->ids('job_type_id'),
            $this->ids('job_level_id'),
            $this->ids('experience_level_id'),
            $this->ids('education_level_id'),
            $this->ids('work_time_id'),
            array_map(static fn (string $mode): WorkMode => WorkMode::from($mode), $this->values('work_mode')),
            $this->ids('province_id'),
            $this->ids('city_id'),
            $this->ids('skill_id'),
            $this->ids('company_id'),
            $this->filled('filter.salary_min') ? $this->integer('filter.salary_min') : null,
            $this->filled('filter.salary_period_id') ? $this->integer('filter.salary_period_id') : null,
            $this->sort(),
            $this->filled('filter.posted_within') ? $this->integer('filter.posted_within') : null,
        );
    }

    /** The asked order; relevance is the default when there is a keyword, newest first otherwise. */
    private function sort(): JobSearchSort
    {
        if ($this->filled('sort')) {
            return JobSearchSort::from($this->string('sort')->toString());
        }

        return $this->words() === [] ? JobSearchSort::PublishedAtDescending : JobSearchSort::Relevance;
    }

    /** @return list<string> the keyword's words, each once whatever the letter case, in the order typed */
    private function words(): array
    {
        $words = [];

        foreach (preg_split('/\s+/u', trim($this->string('filter.q')->toString()), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $words[mb_strtolower($word)] = $word;
        }

        return array_values($words);
    }

    /** @return list<int> */
    private function ids(string $filter): array
    {
        return array_values(array_unique(array_map('intval', $this->values($filter))));
    }

    /** @return list<string> */
    private function values(string $filter): array
    {
        $raw = $this->input("filter.{$filter}");

        return is_string($raw) && $raw !== '' ? array_values(array_unique(array_map('trim', explode(',', $raw)))) : [];
    }

    private function maxWords(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && count(preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: []) > self::MAX_WORDS) {
                $fail('The search can have at most '.self::MAX_WORDS.' words.');
            }
        };
    }

    /** @param  list<string>|null  $allowed  the values a list may hold; null means positive whole numbers */
    private function listOf(?array $allowed): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            $values = is_string($value) ? array_map('trim', explode(',', $value)) : [];

            if ($values === [] || count($values) > self::MAX_VALUES) {
                $fail('Give 1 to '.self::MAX_VALUES.' values separated by commas.');

                return;
            }

            foreach ($values as $item) {
                $valid = $allowed === null ? preg_match('/^[1-9][0-9]{0,18}$/', $item) === 1 : in_array($item, $allowed, true);

                if (! $valid) {
                    $fail($allowed === null ? 'Every value must be a positive whole number.' : 'Every value must be one of: '.implode(', ', $allowed).'.');

                    return;
                }
            }
        };
    }
}
