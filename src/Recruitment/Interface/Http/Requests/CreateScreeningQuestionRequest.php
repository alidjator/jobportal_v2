<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\QuestionType;

/** The shape only: the length limits and the rules about choices are checked by the use case, which answers 422 `invalid-screening-question`. */
final class CreateScreeningQuestionRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(QuestionType::class)],
            'text' => ['required', 'string', 'max:1000'],
            'options' => ['nullable', 'array', 'max:50'],
            'options.*' => ['array:label'],
            'options.*.label' => ['required', 'string', 'max:1000'],
        ];
    }

    public function type(): QuestionType
    {
        return QuestionType::from($this->string('type')->toString());
    }

    public function text(): string
    {
        return $this->string('text')->toString();
    }

    /** @return list<string> */
    public function labels(): array
    {
        return array_values(array_map(static fn (array $option): string => (string) $option['label'], $this->array('options')));
    }
}
