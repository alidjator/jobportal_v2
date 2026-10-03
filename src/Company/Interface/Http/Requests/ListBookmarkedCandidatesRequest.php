<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBookmarkedCandidatesRequest extends FormRequest
{
    public function rules(): array
    {
        return ['category' => ['nullable', 'regex:/^(all|\d+)$/']];
    }

    public function categoryId(): ?int
    {
        $category = $this->query('category');

        return is_numeric($category) ? (int) $category : null;
    }
}
