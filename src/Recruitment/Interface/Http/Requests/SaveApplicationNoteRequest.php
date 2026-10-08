<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The text of an internal note; its length is a domain rule (422 `invalid-note`). */
final class SaveApplicationNoteRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string']];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
