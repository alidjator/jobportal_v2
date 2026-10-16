<?php

namespace Src\Job\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Job\Application\JobListSort;
use Src\Job\Domain\JobStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[q]` (title), `filter[status]` (one of the seven statuses), `filter[assigned_to_me]` (true or false), `filter[deadline_to]` (a day: deadline on or before it), `filter[applications]` (only `0`: jobs nobody applied to), `sort` (`updated_at`, `created_at`,
 * `title`, `deadline`, each with an optional `-`; default `-updated_at`), `page` and `per_page`. Any other filter is refused with 422.
 */
final class ListJobsRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,status,assigned_to_me,deadline_to,applications'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'filter.status' => ['nullable', 'string', Rule::enum(JobStatus::class)],
            'filter.assigned_to_me' => ['nullable', 'in:true,false,1,0'],
            'filter.deadline_to' => ['nullable', 'date_format:Y-m-d'],
            'filter.applications' => ['nullable', 'in:0'],
            'sort' => ['nullable', 'string', Rule::enum(JobListSort::class)],
        ];
    }

    public function status(): ?JobStatus
    {
        return $this->filled('filter.status') ? JobStatus::from($this->string('filter.status')->toString()) : null;
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    public function assignedToMe(): bool
    {
        return $this->filled('filter.assigned_to_me') && $this->boolean('filter.assigned_to_me');
    }

    public function deadlineTo(): ?DateTimeImmutable
    {
        return $this->filled('filter.deadline_to') ? new DateTimeImmutable($this->string('filter.deadline_to')->toString(), new DateTimeZone('UTC')) : null;
    }

    /** `filter[applications]=0`: only the jobs nobody applied to. */
    public function withoutApplications(): bool
    {
        return $this->filled('filter.applications');
    }

    public function sort(): JobListSort
    {
        return $this->filled('sort') ? JobListSort::from($this->string('sort')->toString()) : JobListSort::UpdatedAtDescending;
    }
}
