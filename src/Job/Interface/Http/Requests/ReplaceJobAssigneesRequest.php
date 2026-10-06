<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The whole set of account ids that work on the job. Whether they belong to the company is checked by the use case. */
final class ReplaceJobAssigneesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function userIds(): array
    {
        return array_values(array_map('intval', $this->array('user_ids')));
    }
}
