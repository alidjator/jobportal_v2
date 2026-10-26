<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\ReportStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`pending_review` or `signed`), `filter[reviewed]` (boolean), `filter[claimed]` (`mine`, `others` or `none`: by the live claim), `page` and `per_page`. Any other filter is refused with 422. */
final class ListReviewsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status,reviewed,claimed'],
            'filter.status' => ['nullable', 'string', Rule::enum(ReportStatus::class)],
            'filter.reviewed' => ['nullable', 'boolean'],
            'filter.claimed' => ['nullable', 'string', Rule::in(['mine', 'others', 'none'])],
        ];
    }

    /** `filter[reviewed]=0` is the automatically issued reports that wait for a psychologist; `1` the others. */
    public function reviewed(): ?bool
    {
        return $this->filled('filter.reviewed') ? $this->boolean('filter.reviewed') : null;
    }

    public function claimed(): ?string
    {
        return $this->filled('filter.claimed') ? $this->string('filter.claimed')->toString() : null;
    }

    public function status(): ?ReportStatus
    {
        return $this->filled('filter.status') ? ReportStatus::from($this->string('filter.status')->toString()) : null;
    }
}
