<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The body that moves a saved candidate: `category_id`, a category of the company, or `null` for none. The key must be sent. */
final class ChangeCategoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['category_id' => ['present', 'nullable', 'integer', 'min:1']];
    }

    public function categoryId(): ?int
    {
        return $this->filled('category_id') ? $this->integer('category_id') : null;
    }
}
