<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Application\Review\ReviseReport;
use Src\Assessment\Application\Review\UpdateReview;
use Src\Assessment\Domain\Recommendation;

/** The text, the explanation and the reason of a revised report. The text and the explanation are sent every time and must have content (the use case refuses blanks). */
final class ReviseReportRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.UpdateReview::TEXT_MAX],
            'explanation' => ['required', 'string', 'max:'.UpdateReview::EXPLANATION_MAX],
            /** Left out, the report keeps its recommendation. */
            'recommendation' => ['sometimes', 'nullable', Rule::enum(Recommendation::class)],
            /** Why the report is revised: required by the use case (422 `reason-required`), at most 1,000 characters; the company and the candidate never read it. */
            'revision_reason' => ['sometimes', 'nullable', 'string', 'max:'.ReviseReport::REASON_MAX],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }

    public function recommendation(): ?Recommendation
    {
        return $this->filled('recommendation') ? Recommendation::from($this->string('recommendation')->toString()) : null;
    }

    public function reason(): ?string
    {
        return $this->filled('revision_reason') ? $this->string('revision_reason')->toString() : null;
    }

    public function explanation(): string
    {
        return $this->string('explanation')->toString();
    }
}
