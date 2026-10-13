<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The whole ordered list of skill ids. Limits and the skills themselves are checked by the use case. */
final class ReplaceMySkillsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'skill_ids' => ['present', 'array', 'max:255'],
            'skill_ids.*' => ['integer', 'min:1'],
        ];
    }

    /** @return list<int> */
    public function skillIds(): array
    {
        return array_values(array_map('intval', $this->array('skill_ids')));
    }
}
