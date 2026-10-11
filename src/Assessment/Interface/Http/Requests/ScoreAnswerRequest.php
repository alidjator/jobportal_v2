<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** An assessor's score for one written answer: a whole number, checked against the points of the question by the use case. */
final class ScoreAnswerRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['score' => ['required', 'integer', 'min:0', 'max:1000']];
    }

    public function score(): int
    {
        return $this->integer('score');
    }
}
