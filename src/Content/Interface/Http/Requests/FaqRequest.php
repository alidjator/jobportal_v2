<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FaqRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'min:1'],
            'question' => ['required', 'array'],
            'question.id' => ['required', 'string', 'max:500'],
            'question.en' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'array'],
            'answer.id' => ['required', 'string', 'max:20000'],
            'answer.en' => ['required', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function categoryId(): int
    {
        return $this->integer('category_id');
    }

    /** @return array<array-key, mixed> */
    public function question(): array
    {
        return $this->array('question');
    }

    /** @return array<array-key, mixed> */
    public function answer(): array
    {
        return $this->array('answer');
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
