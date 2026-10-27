<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `filter[from]`, `filter[to]`, `filter[psychologist_id]`, `filter[test_id]`, `page` and `per_page`. Any other filter is refused with 422. */
final class ListRegisterRequest extends FormRequest
{
    use PaginatedRequest;
    use ReadsRegisterFilter;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->paginationRules(), ...$this->registerFilterRules()];
    }
}
