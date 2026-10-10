<?php

namespace Src\Newsletter\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Newsletter\Domain\Issue;

/** An issue to create or change: `subject` (up to 150 characters) and `body` (Markdown, up to 20,000 characters). Raw HTML in the body is shown as text in the email. */
final class SaveIssueRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:'.Issue::SUBJECT_MAX],
            'body' => ['required', 'string', 'max:'.Issue::BODY_MAX],
        ];
    }

    public function subject(): string
    {
        return $this->string('subject')->toString();
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
