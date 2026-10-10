<?php

namespace Src\Newsletter\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Newsletter\Domain\IssueStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`draft`, `sending`, `sent`), `page` and `per_page`. Any other filter is refused with 422. Newest first. */
final class ListIssuesRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(IssueStatus::class)],
        ];
    }

    public function status(): ?IssueStatus
    {
        return $this->filled('filter.status') ? IssueStatus::from($this->string('filter.status')->toString()) : null;
    }
}
