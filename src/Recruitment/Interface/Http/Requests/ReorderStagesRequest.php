<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The ids of every stage in the wanted order. Whether they match the company's stages is checked by the use case. */
final class ReorderStagesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'stage_ids' => ['required', 'array', 'min:1', 'max:50'],
            'stage_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function stageIds(): array
    {
        return array_values(array_map('intval', $this->array('stage_ids')));
    }
}
