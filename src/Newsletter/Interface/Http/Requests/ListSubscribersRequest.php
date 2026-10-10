<?php

namespace Src\Newsletter\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Newsletter\Domain\SubscriberStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[q]` (part of the address), `filter[status]`, `page` and `per_page`. Any other filter is refused with 422. Newest first. */
final class ListSubscribersRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,status'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'filter.status' => ['nullable', 'string', Rule::enum(SubscriberStatus::class)],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    public function status(): ?SubscriberStatus
    {
        return $this->filled('filter.status') ? SubscriberStatus::from($this->string('filter.status')->toString()) : null;
    }
}
