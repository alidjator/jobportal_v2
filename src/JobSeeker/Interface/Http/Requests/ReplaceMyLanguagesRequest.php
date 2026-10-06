<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobSeeker\Domain\LanguageEntry;
use Src\JobSeeker\Domain\LanguageProficiency;

/** The whole list of languages with a proficiency each. Limits, repeats and the languages themselves are checked by the use case. */
final class ReplaceMyLanguagesRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'languages' => ['present', 'array', 'max:100'],
            'languages.*' => ['array:language_id,proficiency'],
            'languages.*.language_id' => ['required', 'integer', 'min:1'],
            'languages.*.proficiency' => ['required', 'string', Rule::enum(LanguageProficiency::class)],
        ];
    }

    /** @return list<LanguageEntry> */
    public function languages(): array
    {
        return array_values(array_map(
            static fn (array $item): LanguageEntry => new LanguageEntry((int) $item['language_id'], LanguageProficiency::from((string) $item['proficiency'])),
            $this->array('languages'),
        ));
    }
}
