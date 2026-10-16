<?php

namespace Src\Workforce\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The `reason` a requisition is closed by hand. */
final class CloseRequisitionRequest extends FormRequest
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
