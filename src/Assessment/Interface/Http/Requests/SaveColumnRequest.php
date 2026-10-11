<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The sums of a column in order: the last digit of each pair of neighbouring digits, or null where the candidate got no further. */
final class SaveColumnRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'max:200'],
            'answers.*' => ['nullable', 'integer', 'between:0,9'],
        ];
    }

    /** @return list<int|null> */
    public function sums(): array
    {
        return array_values(array_map(static fn (mixed $sum): ?int => $sum === null ? null : (int) $sum, (array) $this->input('answers')));
    }
}
