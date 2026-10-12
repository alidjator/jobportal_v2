<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\Recommendation;

/**
 * The whole content of the caller's scorecard. The shape only: the score range, the length limits and whether the questions belong to the interview are
 * domain rules (422 `invalid-scorecard`). Answers left out are skipped questions.
 */
final class SaveScorecardRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'recommendation' => ['nullable', 'string', Rule::enum(Recommendation::class)],
            'note' => ['nullable', 'string'],
            'answers' => ['nullable', 'array', 'max:100'],
            'answers.*' => ['array:question_id,score,note'],
            'answers.*.question_id' => ['required', 'integer', 'min:1'],
            'answers.*.score' => ['nullable', 'integer'],
            'answers.*.note' => ['nullable', 'string'],
        ];
    }

    public function recommendation(): ?Recommendation
    {
        $value = $this->input('recommendation');

        return is_string($value) ? Recommendation::from($value) : null;
    }

    public function note(): ?string
    {
        $value = $this->input('note');

        return is_string($value) ? $value : null;
    }

    /** @return list<array{question_id: int, score: ?int, note: ?string}> */
    public function answers(): array
    {
        return array_values(array_map(static fn (array $answer): array => [
            'question_id' => (int) $answer['question_id'],
            'score' => isset($answer['score']) ? (int) $answer['score'] : null,
            'note' => isset($answer['note']) ? (string) $answer['note'] : null,
        ], $this->array('answers')));
    }
}
