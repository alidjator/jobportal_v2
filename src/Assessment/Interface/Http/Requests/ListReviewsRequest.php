<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\ReportStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`pending_review` or `signed`), `page` and `per_page`. Any other filter is refused with 422. */
final class ListReviewsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(ReportStatus::class)],
        ];
    }

    public function status(): ?ReportStatus
    {
        return $this->filled('filter.status') ? ReportStatus::from($this->string('filter.status')->toString()) : null;
    }
}
