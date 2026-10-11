<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The ids of every section (or question) in the wanted order. Whether they match is checked by the use case. */
final class OrderRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_map('intval', $this->array('ids')));
    }
}
