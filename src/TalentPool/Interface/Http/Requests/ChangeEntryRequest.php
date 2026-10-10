<?php

namespace Src\TalentPool\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Src\TalentPool\Application\EntryChanges;
use Src\TalentPool\Domain\Entry;

/**
 * The body that changes a saved candidate: `category_id` (a category of the company, `null` for none) and/or `note` (plain text, `null` or empty to clear it).
 * A key that is not sent is left as it is; at least one must be sent. The length of the note is checked by the domain.
 */
final class ChangeEntryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:'.(Entry::MAX_NOTE_LENGTH * 4)],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->has('category_id') && ! $this->has('note')) {
                $validator->errors()->add('category_id', 'Send category_id, note or both.');
            }
        }];
    }

    public function changes(): EntryChanges
    {
        return new EntryChanges(
            $this->has('category_id'),
            $this->filled('category_id') ? $this->integer('category_id') : null,
            $this->has('note'),
            $this->filled('note') ? $this->string('note')->toString() : null,
        );
    }
}
