<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Requests\PaginatedRequest;

/** Allowed: `page` and `per_page`. */
final class ListPsychologistsRequest extends FormRequest
{
    use PaginatedRequest;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
