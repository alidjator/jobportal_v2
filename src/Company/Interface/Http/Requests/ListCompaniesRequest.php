<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Application\Admin\CompanyListQuery;
use Src\Company\Application\Admin\CompanyListSort;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/**
 * Allowed: `filter[q]` (name or slug), `filter[status]` (`active` or `suspended`), `filter[verified]` (true or false), `filter[linked]` (true: the tenant of a legal entity of an ERP group; false: not yet), `sort` (`name`, `-name`,
 * `created_at`, `-created_at`; default newest first), `page` and `per_page`. Any other filter is refused with 422.
 */
final class ListCompaniesRequest extends FormRequest
{
    use PaginatedRequest;

    private const int MAX_SEARCH_LENGTH = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'filter' => ['nullable', 'array:q,status,verified,linked'],
            'filter.q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'filter.status' => ['nullable', 'string', Rule::enum(CompanyStatus::class)],
            'filter.verified' => ['nullable', 'in:true,false,1,0'],
            'filter.linked' => ['nullable', 'in:true,false,1,0'],
            'sort' => ['nullable', 'string', Rule::enum(CompanyListSort::class)],
        ];
    }

    public function toQuery(): CompanyListQuery
    {
        return new CompanyListQuery(
            $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null,
            $this->filled('filter.status') ? CompanyStatus::from($this->string('filter.status')->toString()) : null,
            $this->filled('filter.verified') ? $this->boolean('filter.verified') : null,
            $this->filled('sort') ? CompanyListSort::from($this->string('sort')->toString()) : CompanyListSort::NewestFirst,
            $this->filled('filter.linked') ? $this->boolean('filter.linked') : null,
        );
    }
}
