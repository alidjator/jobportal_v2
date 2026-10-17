<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed filter: `state` (`pending` or `answered`). Any other filter is refused with 422. Paginated (`page`, `per_page`). */
final class ListMyReviewRequestsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:state'],
            'filter.state' => ['nullable', 'string', 'in:pending,answered'],
        ];
    }

    public function state(): ?string
    {
        $state = $this->input('filter.state');

        return is_string($state) ? $state : null;
    }
}
