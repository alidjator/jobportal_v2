<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FaqCategoryRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.id' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<array-key, mixed> */
    public function name(): array
    {
        return $this->array('name');
    }

    public function sortOrder(): int
    {
        return $this->integer('sort_order');
    }

    public function isActive(): bool
    {
        return $this->boolean('is_active', true);
    }
}
