<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\TalentPool\Domain\Entry;

/**
 * The body that saves a candidate: `candidate` (the slug from the candidate search), an optional `category_id` of the company's own and an optional `note`
 * (plain text; its length is checked by the domain).
 */
final class SaveCandidateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate' => ['required', 'string', 'max:40'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:'.(Entry::MAX_NOTE_LENGTH * 4)],
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

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }
}
