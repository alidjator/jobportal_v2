<?php

namespace Src\Approval\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The reason of a rejection or of a return for revision: required, 5 to 500 characters. */
final class ApprovalReasonRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
