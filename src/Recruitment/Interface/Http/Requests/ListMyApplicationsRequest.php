<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Interface\Http\Requests\CursorPaginatedRequest;

/** Allowed filter: `status`, a stage type. Any other filter is refused with 422. Newest first, cursor-paginated (`cursor`, `per_page`). */
final class ListMyApplicationsRequest extends FormRequest
{
    use CursorPaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->cursorRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(StageType::class)],
        ];
    }

    public function statusFilter(): ?StageType
    {
        return $this->filled('filter.status') ? StageType::from($this->string('filter.status')->toString()) : null;
    }
}
