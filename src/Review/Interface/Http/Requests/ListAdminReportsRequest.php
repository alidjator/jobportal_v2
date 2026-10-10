<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Review\Domain\ReportStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`open` by default, `dismissed`, `actioned`), `page`, `per_page`. */
final class ListAdminReportsRequest extends FormRequest
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

    public function status(): ReportStatus
    {
        return $this->filled('filter.status') ? ReportStatus::from($this->string('filter.status')->toString()) : ReportStatus::Open;
    }
}
