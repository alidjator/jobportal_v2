<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Create or update a document type. An update sends the full body; the slug is never part of it. */
final class DocumentTypeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.id' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<array-key, mixed> */
    public function name(): array
    {
        return $this->array('name');
    }

    public function isRequired(): bool
    {
        return $this->boolean('is_required', false);
    }

    public function isActive(): bool
    {
        return $this->boolean('is_active', true);
    }

    public function sortOrder(): int
    {
        return $this->integer('sort_order');
    }
}
