<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A multipart upload with one field, `file`. Only the shape is checked here: that a file arrived whole. Its type, its size against the admin
 * setting and its content are checked by the use case, which reads the content itself and ignores the file name and the type the client sent.
 */
final class UploadCompanyImageRequest extends FormRequest
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
