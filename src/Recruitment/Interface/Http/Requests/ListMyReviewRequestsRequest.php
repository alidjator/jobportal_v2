<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed filters: `state` (`pending`, `answered` or `overdue`) and `role` (`reviewer`, the default, or `requester`: the requests the caller made). Any other filter is refused with 422.
 * Paginated (`page`, `per_page`).
 */
final class ListMyReviewRequestsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:state,role'],
            'filter.state' => ['nullable', 'string', 'in:pending,answered,overdue'],
            'filter.role' => ['nullable', 'string', 'in:reviewer,requester'],
        ];
    }

    public function state(): ?string
    {
        $state = $this->input('filter.state');

        return is_string($state) ? $state : null;
    }

    public function role(): string
    {
        return $this->input('filter.role') === 'requester' ? 'requester' : 'reviewer';
    }
}
