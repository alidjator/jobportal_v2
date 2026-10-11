<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Application\Review\ScoreDrawing;

/** The psychologist's score for a drawing (from 0 to the points of the question, checked by the use case) and an optional note. */
final class ScoreDrawingRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:'.ScoreDrawing::NOTE_MAX],
        ];
    }

    public function score(): float
    {
        return (float) $this->input('score');
    }

    public function note(): ?string
    {
        return $this->input('note');
    }
}
