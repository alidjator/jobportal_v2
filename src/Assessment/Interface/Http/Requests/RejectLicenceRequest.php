<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RejectLicenceRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:3', 'max:1000']];
    }

    public function note(): string
    {
        return $this->string('note')->toString();
    }
}
