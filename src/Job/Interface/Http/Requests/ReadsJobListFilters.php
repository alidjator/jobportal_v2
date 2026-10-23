<?php

namespace Src\Job\Interface\Http\Requests;

use Src\Job\Application\JobListFilters;
use Src\Job\Domain\WorkMode;

/**
 * The extra filters of the company's job list and of its status counts (`docs/design/phase-8-job-posting.md` section 4). A malformed value is refused with 422 by the validation
 * of the request that uses this trait.
 */
trait ReadsJobListFilters
{
    /** The filter keys to add to the `array:` rule of the request. */
    private const string EXTRA_FILTER_KEYS = 'province_id,city_id,work_mode,job_type_id,category_id,published_from,published_to,deadline_from,created_by,assignee,filled';

    private const string JOB_ID_LIST = '/^[1-9][0-9]*(,[1-9][0-9]*)*$/';

    /** @return array<string, mixed> */
    protected function extraFilterRules(): array
    {
        $modes = implode('|', array_map(static fn (WorkMode $mode): string => $mode->value, WorkMode::cases()));

        return [
            /** Comma-separated place ids; a job with several work locations matches through any of them. */
            'filter.province_id' => ['nullable', 'string', 'max:200', 'regex:'.self::JOB_ID_LIST],
            'filter.city_id' => ['nullable', 'string', 'max:200', 'regex:'.self::JOB_ID_LIST],
            /** Comma-separated work modes: `onsite`, `hybrid`, `remote`. */
            'filter.work_mode' => ['nullable', 'string', "regex:/^({$modes})(,({$modes}))*$/"],
            'filter.job_type_id' => ['nullable', 'string', 'max:200', 'regex:'.self::JOB_ID_LIST],
            'filter.category_id' => ['nullable', 'string', 'max:200', 'regex:'.self::JOB_ID_LIST],
            'filter.published_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.published_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.published_from'],
            'filter.deadline_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.created_by' => ['nullable', 'integer', 'min:1'],
            'filter.assignee' => ['nullable', 'integer', 'min:1'],
            'filter.filled' => ['nullable', 'in:true,false,1,0'],
        ];
    }

    public function extraFilters(): JobListFilters
    {
        return new JobListFilters(
            $this->idListOf('filter.province_id'),
            $this->idListOf('filter.city_id'),
            $this->filled('filter.work_mode') ? array_map(WorkMode::from(...), array_values(array_unique(explode(',', $this->string('filter.work_mode')->toString())))) : [],
            $this->idListOf('filter.job_type_id'),
            $this->idListOf('filter.category_id'),
            $this->filled('filter.published_from') ? $this->string('filter.published_from')->toString() : null,
            $this->filled('filter.published_to') ? $this->string('filter.published_to')->toString() : null,
            $this->filled('filter.deadline_from') ? $this->string('filter.deadline_from')->toString() : null,
            $this->filled('filter.created_by') ? $this->integer('filter.created_by') : null,
            $this->filled('filter.assignee') ? $this->integer('filter.assignee') : null,
            $this->filled('filter.filled') ? $this->boolean('filter.filled') : null,
        );
    }

    /** @return list<int> */
    private function idListOf(string $key): array
    {
        return $this->filled($key) ? array_values(array_unique(array_map('intval', explode(',', $this->string($key)->toString())))) : [];
    }
}
