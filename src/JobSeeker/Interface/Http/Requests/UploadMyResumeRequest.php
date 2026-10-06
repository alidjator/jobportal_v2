<?php

namespace Src\JobSeeker\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A multipart upload with one field, `file`. Only the shape is checked here: that a file arrived whole. Its size and content are checked by the use case, which
 * reads the content itself and ignores the type the client sent. The client's file name is passed on for display only.
 */
final class UploadMyResumeRequest extends FormRequest
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
