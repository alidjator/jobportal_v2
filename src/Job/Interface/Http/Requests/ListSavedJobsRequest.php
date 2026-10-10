<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `page` and `per_page`. */
final class ListSavedJobsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
