<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A multipart body: the `file` (JPEG, PNG or WebP; the use case reads its content) and the `alt` text for screen readers. */
final class UploadQuestionImageRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'alt' => ['required', 'string', 'max:1000'],
        ];
    }

    public function fileContents(): string
    {
        return (string) $this->file('file')?->getContent();
    }

    public function alt(): string
    {
        return $this->string('alt')->toString();
    }
}
