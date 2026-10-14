<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Application\Review\UpdateReview;

/** The text and the explanation of a revised report. Both are sent every time and must have content (the use case refuses blanks). */
final class ReviseReportRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.UpdateReview::TEXT_MAX],
            'explanation' => ['required', 'string', 'max:'.UpdateReview::EXPLANATION_MAX],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }

    public function explanation(): string
    {
        return $this->string('explanation')->toString();
    }
}
