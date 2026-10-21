<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Company\Application\Admin\CompanyListQuery;

/** Allowed: `filter[q]` (name or slug) and `filter[verified]` (true or false), as in the list. Any other filter is refused with 422. */
final class CountCompaniesRequest extends FormRequest
{
    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:q,verified'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'filter.verified' => ['nullable', 'in:true,false,1,0'],
        ];
    }

    public function toQuery(): CompanyListQuery
    {
        return new CompanyListQuery(
            $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null,
            null,
            $this->filled('filter.verified') ? $this->boolean('filter.verified') : null,
        );
    }
}
