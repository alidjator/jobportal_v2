<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InviteCompanyAdminRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }
}
