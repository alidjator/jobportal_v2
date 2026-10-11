<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** One answer: `option_positions` (1-based) for a choice question, `number` or `text`. Whether the field fits the question is a use case rule. */
final class SaveAnswerRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'option_positions' => ['nullable', 'array', 'max:50'],
            'option_positions.*' => ['integer', 'min:1', 'max:50'],
            'number' => ['nullable', 'numeric'],
            'text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /** @return list<int>|null */
    public function optionPositions(): ?array
    {
        return $this->has('option_positions') && $this->input('option_positions') !== null ? array_values(array_map('intval', (array) $this->input('option_positions'))) : null;
    }

    public function number(): ?float
    {
        $number = $this->input('number');

        return $number === null ? null : (float) $number;
    }

    public function text(): ?string
    {
        $text = $this->input('text');

        return $text === null ? null : (string) $text;
    }
}
