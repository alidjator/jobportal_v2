<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Domain\DrawingSource;

/** A multipart upload: the `file` and where it comes from, `canvas` or `upload`. Only the shape is checked here; the use case reads the content itself. */
final class UploadDrawingRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'source' => ['required', 'string', Rule::enum(DrawingSource::class)],
        ];
    }

    public function contents(): string
    {
        return (string) $this->file('file')?->getContent();
    }

    public function source(): DrawingSource
    {
        return DrawingSource::from($this->string('source')->toString());
    }
}
