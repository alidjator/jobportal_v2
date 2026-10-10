<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `page` and `per_page`. Newest reviews come first. */
final class ListMyReviewsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
