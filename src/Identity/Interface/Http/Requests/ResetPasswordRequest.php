<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }

    public function newPassword(): string
    {
        return $this->string('password')->toString();
    }
}
