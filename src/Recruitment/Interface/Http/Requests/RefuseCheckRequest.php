<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\ApplicationCheck;

/** The candidate's refusal of a check, with an optional `reason` the team sees. */
final class RefuseCheckRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:'.(ApplicationCheck::REASON_MAX * 2)],
        ];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->toString() : null;
    }
}
