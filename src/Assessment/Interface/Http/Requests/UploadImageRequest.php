<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A multipart upload with one field, `file`. Only the shape is checked here; the use case reads the content, checks its size and type and encodes the picture again. */
final class UploadImageRequest extends FormRequest
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
}
