<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A multipart upload with the `file` of the example report. Only the shape is checked here; the use case reads the content (a PDF of at most 10 MB) and ignores the type the client sent. */
final class ExampleReportRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file']];
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
