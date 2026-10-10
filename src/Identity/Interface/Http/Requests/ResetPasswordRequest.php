<?php

namespace Src\Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Interface\Http\Rules\NewPassword;

final class ResetPasswordRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => NewPassword::rules(),
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
