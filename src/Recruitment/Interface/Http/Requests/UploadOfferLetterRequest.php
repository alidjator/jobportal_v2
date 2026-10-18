<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A multipart upload with one field, `file`. Only the shape is checked here: that a file arrived whole. Its size and content (a PDF, by its signature, never by
 * its name or the type the client sent) are checked by the use case.
 */
final class UploadOfferLetterRequest extends FormRequest
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
