<?php

namespace Src\Workforce\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The file of a requisition document; its type and content are checked by the use case, so a wrong one answers `invalid-file`. */
final class UploadRequisitionDocumentRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file']];
    }

    public function fileContents(): string
    {
        return (string) $this->file('file')?->getContent();
    }

    public function fileName(): string
    {
        return (string) $this->file('file')?->getClientOriginalName();
    }
}
