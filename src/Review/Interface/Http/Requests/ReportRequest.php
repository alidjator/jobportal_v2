<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Review\Domain\ReportReason;

final class ReportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(ReportReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from($this->string('reason')->toString());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? trim($this->string('note')->toString()) : null;
    }
}
