<?php

namespace Src\Job\Interface\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Allowed: the filters of the job list except the status, with the same meaning: `filter[q]`, `filter[assigned_to_me]`, `filter[deadline_to]` and
 * `filter[applications]` (only `0`). Any other filter is refused with 422.
 */
final class CountJobsRequest extends FormRequest
{
    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:q,assigned_to_me,deadline_to,applications'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'filter.assigned_to_me' => ['nullable', 'in:true,false,1,0'],
            'filter.deadline_to' => ['nullable', 'date_format:Y-m-d'],
            'filter.applications' => ['nullable', 'in:0'],
        ];
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

    public function withoutApplications(): bool
    {
        return $this->filled('filter.applications');
    }
}
