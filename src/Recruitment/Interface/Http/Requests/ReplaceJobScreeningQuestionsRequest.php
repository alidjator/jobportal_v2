<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The whole ordered list of questions of a job. The shape only: what a question may be asked, and the ideal answers, are checked by the use case. */
final class ReplaceJobScreeningQuestionsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'questions' => ['present', 'array', 'max:50'],
            'questions.*' => ['array:question_id,required,must_have,ideal'],
            'questions.*.question_id' => ['required', 'integer', 'min:1', 'distinct'],
            'questions.*.required' => ['sometimes', 'boolean'],
            'questions.*.must_have' => ['sometimes', 'boolean'],
            'questions.*.ideal' => ['nullable', 'array'],
        ];
    }

    /** @return list<array{question_id: int, required: bool, must_have: bool, ideal: array<string, mixed>|null}> */
    public function items(): array
    {
        return array_values(array_map(static fn (array $item): array => [
            'question_id' => (int) $item['question_id'],
            'required' => (bool) ($item['required'] ?? false),
            'must_have' => (bool) ($item['must_have'] ?? false),
            'ideal' => $item['ideal'] ?? null,
        ], $this->array('questions')));
    }
}
