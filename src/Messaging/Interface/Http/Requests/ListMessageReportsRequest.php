<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Messaging\Domain\ReportStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** `filter[status]` narrows to open, dismissed or actioned reports. Any other filter is refused. */
final class ListMessageReportsRequest extends FormRequest
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
