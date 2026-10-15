<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A multipart request: `new_name`, `reason` and `file` (the proof, a PDF). Only the shape is checked here; the length of the name and the reason are checked by
 * the use case, which reads the file content itself and ignores the type the client sent.
 */
final class RequestNameChangeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'new_name' => ['required', 'string'],
            'reason' => ['required', 'string'],
            'file' => ['required', 'file'],
        ];
    }

    public function newName(): string
    {
        return $this->string('new_name')->toString();
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }

    public function contents(): string
    {
        return (string) $this->file('file')?->getContent();
    }

    public function originalName(): string
    {
        return (string) $this->file('file')?->getClientOriginalName();
    }
}
