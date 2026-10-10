<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Review\Domain\ReplyStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[status]` (`pending`, `published`, `rejected`), `page`, `per_page`. */
final class ListAdminRepliesRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', 'string', Rule::enum(ReplyStatus::class)],
        ];
    }

    public function status(): ?ReplyStatus
    {
        return $this->filled('filter.status') ? ReplyStatus::from($this->string('filter.status')->toString()) : null;
    }
}
