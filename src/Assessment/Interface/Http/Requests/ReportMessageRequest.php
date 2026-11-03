<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Domain\ReportMessage;

/** A question or an answer about a signed report: plain text, at most 2,000 characters. */
final class ReportMessageRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.ReportMessage::BODY_MAX],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
