<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\TalentPool\Domain\Entry;

/**
 * The body that saves a person: either `candidate` (the slug from the candidate search) or `application_id` (an application of the company the member may see),
 * never both, plus an optional `category_id` of the company's own and an optional `note` (plain text; its length is checked by the domain).
 */
final class SaveCandidateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate' => ['required_without:application_id', 'prohibits:application_id', 'nullable', 'string', 'max:40'],
            'application_id' => ['required_without:candidate', 'prohibits:candidate', 'nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:'.(Entry::MAX_NOTE_LENGTH * 4)],
        ];
    }

    public function candidate(): ?string
    {
        return $this->filled('candidate') ? $this->string('candidate')->toString() : null;
    }

    public function applicationId(): ?int
    {
        return $this->filled('application_id') ? $this->integer('application_id') : null;
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
