<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\ProviderStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[q]` (name or legal name), `filter[status]` (`active` or `suspended`), `page` and `per_page`. Any other filter is refused with 422. */
final class ListProvidersRequest extends FormRequest
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
            'filter.status' => ['nullable', 'string', Rule::enum(ProviderStatus::class)],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null;
    }

    public function status(): ?ProviderStatus
    {
        return $this->filled('filter.status') ? ProviderStatus::from($this->string('filter.status')->toString()) : null;
    }
}
