<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Application\Review\UpdateReview;

/** The draft report as the psychologist edited it: the text, the explanation for the candidate and the notes. The three fields are sent every time. */
final class UpdateReviewRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.UpdateReview::TEXT_MAX],
            'explanation' => ['present', 'nullable', 'string', 'max:'.UpdateReview::EXPLANATION_MAX],
            'review_notes' => ['present', 'nullable', 'string', 'max:'.UpdateReview::NOTES_MAX],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }

    public function explanation(): ?string
    {
        return $this->input('explanation');
    }

    public function reviewNotes(): ?string
    {
        return $this->input('review_notes');
    }
}
