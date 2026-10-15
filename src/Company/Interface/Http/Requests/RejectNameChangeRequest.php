<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The note an admin gives when rejecting a name change request: required, 5 to 1,000 characters. The company admins read it. */
final class RejectNameChangeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:5', 'max:1000']];
    }

    public function note(): string
    {
        return trim($this->string('note')->toString());
    }
}
