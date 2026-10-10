<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The body that saves a candidate: `candidate` (the slug from the candidate search) and an optional `category_id` of the company's own. */
final class SaveCandidateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate' => ['required', 'string', 'max:40'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function candidate(): string
    {
        return $this->string('candidate')->toString();
    }

    public function categoryId(): ?int
    {
        return $this->filled('category_id') ? $this->integer('category_id') : null;
    }
}
