<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\CheckOutcome;

/** A multipart body that records the result of a check: `outcome`, an optional `note` (team only) and an optional `file` (a PDF; the use case reads its content). */
final class RecordCheckResultRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::enum(CheckOutcome::class)],
            'note' => ['nullable', 'string', 'max:'.(ApplicationCheck::NOTE_MAX * 2)],
            'file' => ['nullable', 'file'],
        ];
    }

    public function outcome(): CheckOutcome
    {
        return CheckOutcome::from($this->string('outcome')->toString());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }

    public function fileContents(): ?string
    {
        return $this->hasFile('file') ? (string) $this->file('file')?->getContent() : null;
    }

    public function fileName(): ?string
    {
        return $this->hasFile('file') ? (string) $this->file('file')?->getClientOriginalName() : null;
    }
}
