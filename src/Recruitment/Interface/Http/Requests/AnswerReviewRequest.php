<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\ReviewAnswer;
use Src\Recruitment\Domain\ReviewDecision;

/** The shape only: the reason a `decline` needs is checked by the use case (422 `reason-required`). */
final class AnswerReviewRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::enum(ReviewDecision::class)],
            'reason' => ['nullable', 'string', 'max:'.ReviewAnswer::REASON_MAX],
        ];
    }

    public function decision(): ReviewDecision
    {
        return ReviewDecision::from($this->string('decision')->toString());
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) ? $reason : null;
    }
}
